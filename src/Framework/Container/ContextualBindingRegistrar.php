<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\Container as GacelaContainer;

use function class_exists;
use function interface_exists;
use function is_callable;
use function is_object;
use function is_string;

/**
 * @internal registers one contextual binding on the underlying container,
 *           narrowing the stored mixed value into what give() is typed for:
 *           class-strings, callables and objects pass through; any other
 *           scalar is wrapped in a closure so the container injects it as-is
 */
final class ContextualBindingRegistrar
{
    /**
     * @param class-string $concrete
     * @param class-string|string $abstract class name, or a '$parameterName' for scalar bindings
     */
    public static function register(
        GacelaContainer $container,
        string $concrete,
        string $abstract,
        mixed $implementation,
    ): void {
        $builder = $container->when($concrete)->needs($abstract);

        if (is_string($implementation) && (class_exists($implementation) || interface_exists($implementation))) {
            $builder->give($implementation);

            return;
        }

        if (is_object($implementation) || is_callable($implementation)) {
            $builder->give($implementation);

            return;
        }

        $builder->give(static fn (): mixed => $implementation);
    }
}
