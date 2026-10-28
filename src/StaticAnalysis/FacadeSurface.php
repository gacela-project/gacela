<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use Gacela\Framework\AbstractFacade;
use Throwable;

use function class_exists;
use function is_subclass_of;

/**
 * Whether a class is a Gacela Facade, read the same way for every host.
 *
 * Asked of the class, not of its name: libraries name their entry points
 * `*Facade` too, and a module's internal `HelperFacade` is no entry point. Plain
 * reflection, as {@see PublicApiSurface} reads attributes, so PHPStan and Psalm
 * agree by construction.
 */
final class FacadeSurface
{
    /**
     * False for anything that cannot be loaded: "cannot tell" is "no finding"
     * in this layer, and autoloading runs project code that may throw.
     */
    public static function isFacade(string $class): bool
    {
        try {
            return class_exists($class) && is_subclass_of($class, AbstractFacade::class);
        } catch (Throwable) {
            return false;
        }
    }
}
