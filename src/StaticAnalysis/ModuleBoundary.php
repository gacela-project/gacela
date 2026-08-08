<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use function array_slice;
use function count;
use function explode;
use function implode;
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
     * @param int          $modulePathSegments how many segments under the root identify a module
     * @param list<string> $sharedNamespaces   exempt shared kernels: references into
     *                                         them are always allowed, and classes
     *                                         inside them are not checked
     */
    public function __construct(
        private readonly string $rootNamespace,
        private readonly int $modulePathSegments,
        private readonly array $sharedNamespaces,
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
        foreach ($this->sharedNamespaces as $sharedNamespace) {
            if ($class === $sharedNamespace || str_starts_with($class, $sharedNamespace . '\\')) {
                return true;
            }
        }

        return false;
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
}
