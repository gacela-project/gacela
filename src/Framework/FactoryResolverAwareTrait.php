<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use RuntimeException;

/**
 * The `__callStatic` and `__call` methods allow defining `getFactory` as static and non-static methods.
 *
 * @psalm-suppress MethodSignatureMismatch
 *
 * @method static AbstractFactory getFactory()
 * @method AbstractFactory getFactory()
 */
trait FactoryResolverAwareTrait
{
    /** @var array<string, AbstractFactory> */
    private static array $factories = [];

    public static function __callStatic(string $name = '', array $arguments = [])
    {
        if ($name === 'getFactory') {
            return self::doGetFactory();
        }

        if (method_exists(static::class, $name)) {
            /** @psalm-suppress ParentNotFound */
            return parent::__callStatic($name, $arguments);
        }

        throw new RuntimeException("Method unknown: '{$name}'");
    }

    public function __call(string $name = '', array $arguments = [])
    {
        if ($name === 'getFactory') {
            return self::doGetFactory();
        }

        if (method_exists(static::class, $name)) {
            /** @psalm-suppress ParentNotFound */
            return parent::__call($name, $arguments);
        }

        throw new RuntimeException("Method unknown: '{$name}'");
    }

    public static function resetCache(): void
    {
        self::$factories = [];
    }

    private static function doGetFactory(): AbstractFactory
    {
        return self::$factories[static::class]
            ??= (new FactoryResolver())->resolve(static::class);
    }
}
