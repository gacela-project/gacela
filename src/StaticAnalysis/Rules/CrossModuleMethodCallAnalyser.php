<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\ModuleBoundary;
use Gacela\StaticAnalysis\ShortName;
use Gacela\StaticAnalysis\Violation;

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
     * @param list<string> $sharedNamespaces namespaces exempt from the boundary check
     */
    public function __construct(
        string $rootNamespace,
        int $modulePathSegments = 1,
        array $sharedNamespaces = [],
    ) {
        $this->boundary = new ModuleBoundary($rootNamespace, $modulePathSegments, $sharedNamespaces);
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
            );
        }

        return $violations;
    }

    private function isFacade(string $class): bool
    {
        $shortName = ShortName::of($class);

        return str_ends_with($shortName, 'Facade') || str_ends_with($shortName, 'FacadeInterface');
    }
}
