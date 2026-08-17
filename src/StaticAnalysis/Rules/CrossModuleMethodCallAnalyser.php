<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\ModuleBoundary;
use Gacela\StaticAnalysis\PublicApiSurface;
use Gacela\StaticAnalysis\ShortName;
use Gacela\StaticAnalysis\Violation;
use Throwable;

use function is_a;
use function sprintf;
use function str_ends_with;

/**
 * The boundary crossing {@see CrossModuleViaFacadeAnalyser} cannot see.
 *
 * That rule matches names written at the call site. In a codebase built the way
 * Gacela asks -- dependencies pushed through Providers and constructors -- a
 * call site names nothing:
 *
 * ```php
 * public function __construct(private readonly InvoiceRepository $invoices) {}
 *
 * public function createProcessor(): Processor
 * {
 *     return new Processor($this->invoices->findAll());  // App\Billing, unnamed here
 * }
 * ```
 *
 * The class appears once, in a constructor type-hint. So the rule that catches
 * the direct instantiation reports green on exactly the codebases most likely to
 * be crossing boundaries.
 *
 * The receiver's classes are resolved by the host and handed in, because that is
 * the one part neither php-parser nor this package can do: PHPStan reads them off
 * `Scope::getType()`, Psalm off its `NodeTypeProvider`.
 */
final class CrossModuleMethodCallAnalyser
{
    private readonly ModuleBoundary $boundary;

    /**
     * @param list<string> $sharedNamespaces  namespaces exempt from the boundary check
     * @param list<string> $ignoreReceivers   classes and interfaces a call may land on
     *                                        whatever module they belong to
     * @param list<string> $publicApiSegments sub-namespace names a module publishes;
     *                                        an empty list leaves `#[PublicApi]` as
     *                                        the only way to export a class
     */
    public function __construct(
        string $rootNamespace,
        int $modulePathSegments = 1,
        array $sharedNamespaces = [],
        private readonly array $ignoreReceivers = [],
        array $publicApiSegments = PublicApiSurface::DEFAULT_SEGMENTS,
    ) {
        $this->boundary = new ModuleBoundary(
            $rootNamespace,
            $modulePathSegments,
            $sharedNamespaces,
            $publicApiSegments,
        );
    }

    /**
     * @param string       $callingClass    the class the call is written in
     * @param list<string> $receiverClasses the classes the receiver's type resolves
     *                                      to; empty when the host could not tell
     *
     * @return list<Violation>
     */
    public function analyse(string $callingClass, array $receiverClasses): array
    {
        if ($this->boundary->isShared($callingClass)) {
            return [];
        }

        $callingModule = $this->boundary->moduleOf($callingClass);
        if ($callingModule === null) {
            return [];
        }

        $violations = [];

        foreach ($receiverClasses as $receiver) {
            // A Facade is the sanctioned way across, and so is the interface a
            // consumer holds instead of the Facade itself.
            if ($this->isFacade($receiver)) {
                continue;
            }

            if ($this->isExempt($receiver)) {
                continue;
            }

            // The owning module published it, so calling it is not a crossing to
            // justify -- that is what publishing a class means. Asked after the
            // configured list, which needs no reflection and so no autoload.
            if ($this->boundary->isPublicApi($receiver)) {
                continue;
            }

            $module = $this->boundary->crossedBy($callingModule, $receiver);
            if ($module === null) {
                continue;
            }

            $violations[] = new Violation(
                sprintf(
                    'Class %s calls a method on %s from another module (%s). Cross-module access must go through a Facade.',
                    $callingClass,
                    $receiver,
                    $module,
                ),
                'gacela.crossModuleMethodCall',
                sprintf("Type-hint %s's Facade, or its interface, instead.", $module),
            );
        }

        return $violations;
    }

    private function isFacade(string $class): bool
    {
        $shortName = ShortName::of($class);

        return str_ends_with($shortName, 'Facade') || str_ends_with($shortName, 'FacadeInterface');
    }

    /**
     * The two crossings that are not collaborations.
     *
     * An **exception** is read, not worked with. A module throws its own type
     * and a neighbour catches it and asks for `getMessage()` -- the boundary a
     * Facade protects is not crossed by that, and reporting it means every
     * `catch` block of a typed exception is a finding. Measured on phel-lang,
     * 24 of the 53 findings the rule raised were exactly this.
     *
     * A **named receiver** is the project saying "this one is a public contract
     * whatever module it lives in". PHPStan's convention for a structural rule
     * is a constructor parameter rather than a screenful of `ignoreErrors`
     * restating decisions already made -- an error-message ignore also silences
     * the *next* crossing that happens to be phrased the same way.
     *
     * `is_a()` with `allow_string` rather than a name comparison, so naming an
     * interface covers what implements it and naming a base covers what extends
     * it. It answers false for a class the analysis cannot load, which is why
     * the exact name is tried first: an entry naming something unloadable still
     * exempts itself.
     */
    private function isExempt(string $receiver): bool
    {
        if (is_a($receiver, Throwable::class, true)) {
            return true;
        }

        foreach ($this->ignoreReceivers as $ignored) {
            /**
             * @psalm-suppress ArgumentTypeCoercion a configured entry is a string
             *                 somebody typed, not a proven class-string; `is_a()`
             *                 answering false for one that names nothing is the
             *                 behaviour the exact match above exists to cover
             */
            if ($receiver === $ignored || is_a($receiver, $ignored, true)) {
                return true;
            }
        }

        return false;
    }
}
