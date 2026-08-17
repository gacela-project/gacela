<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use function array_pop;
use function array_slice;
use function count;
use function explode;
use function implode;
use function in_array;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Where a module begins and ends, in one place.
 *
 * Nothing in a class name says that on its own, which is why the root namespace
 * has to be supplied -- and why the rules built on this are opt-in while the
 * pillar rules are on by default.
 */
final class ModuleBoundary
{
    /**
     * No defaults here on purpose: the rules built on this are the public API
     * and carry them, so a default repeated here would be a second answer to the
     * same question that nothing exercises.
     *
     * `$sharedNamespaces` and `$publicApiSegments` are two different ideas and
     * both are needed. A shared namespace is a *fully-qualified* prefix that
     * belongs to no module -- a shared kernel every module may reach into. A
     * public-api segment is the name of a *sub-namespace under a module*, which
     * that module publishes: `Shared` matches `App\Billing\Shared\Invoice` and
     * `App\Customer\Shared\Address` alike, and neither of those stops belonging
     * to the module that owns it.
     *
     * @param int          $modulePathSegments how many segments under the root identify a module
     * @param list<string> $sharedNamespaces   exempt shared kernels: references into
     *                                         them are always allowed, and classes
     *                                         inside them are not checked
     * @param list<string> $publicApiSegments  namespace segment names that mark a
     *                                         module's public sub-namespaces
     */
    public function __construct(
        private readonly string $rootNamespace,
        private readonly int $modulePathSegments,
        private readonly array $sharedNamespaces,
        private readonly array $publicApiSegments,
    ) {
    }

    /**
     * The module a class belongs to, or null when it belongs to none -- either
     * outside the root namespace entirely, or sitting directly under it with no
     * segment left to name a module.
     */
    public function moduleOf(string $class): ?string
    {
        $prefix = $this->rootNamespace . '\\';
        if (!str_starts_with($class, $prefix)) {
            return null;
        }

        $remainder = substr($class, strlen($prefix));
        $segments = explode('\\', $remainder);
        if (count($segments) <= $this->modulePathSegments) {
            return null;
        }

        return $this->rootNamespace . '\\' . implode('\\', array_slice($segments, 0, $this->modulePathSegments));
    }

    /**
     * Matching is namespace-boundary aware on purpose: on the raw prefix,
     * `App\Shared` would silently exempt `App\SharedFoo` as well.
     */
    public function isShared(string $class): bool
    {
        return NamespaceMatch::anyCovers($this->sharedNamespaces, $class);
    }

    /**
     * Whether the owning module publishes this class -- by attribute, or by
     * putting it in one of the sub-namespaces it publishes by convention.
     *
     * Whole segments are compared, never prefixes: `Event` publishes
     * `App\Billing\Event\InvoiceIssued` and leaves `App\Billing\EventHandler\Foo`
     * exactly as it was. A prefix match there would publish a module's internals
     * on the strength of a naming coincidence, which is the failure mode
     * {@see isShared()} already exists to avoid on the other list.
     *
     * A class in no module is not published by one, whatever it is annotated
     * with: there is no module for it to be the surface of.
     */
    public function isPublicApi(string $class): bool
    {
        if ($this->moduleOf($class) === null) {
            return false;
        }

        if (PublicApiSurface::isDeclaredOn($class)) {
            return true;
        }

        return $this->isUnderAPublicSegment($class);
    }

    /**
     * The module `$referenced` belongs to, when reaching it from `$fromModule`
     * crosses a boundary that has to be justified -- null when it does not.
     */
    public function crossedBy(string $fromModule, string $referenced): ?string
    {
        $module = $this->moduleOf($referenced);

        if ($module === null || $module === $fromModule || $this->isShared($referenced)) {
            return null;
        }

        return $module;
    }

    /**
     * The segments strictly between the module namespace and the class's own
     * short name, matched whole.
     *
     * A class sitting directly in its module -- `App\Billing\BillingFacade` --
     * has none, so nothing there is ever published by convention.
     */
    private function isUnderAPublicSegment(string $class): bool
    {
        if ($this->publicApiSegments === []) {
            return false;
        }

        $module = (string)$this->moduleOf($class);
        $segments = explode('\\', substr($class, strlen($module) + 1));
        // The class's own name is not a namespace segment.
        array_pop($segments);

        foreach ($segments as $segment) {
            if (in_array($segment, $this->publicApiSegments, true)) {
                return true;
            }
        }

        return false;
    }
}
