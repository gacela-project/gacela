<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use RuntimeException;

use function sprintf;

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

    /**
     * @param list<mixed> $arguments
     */
    public static function __callStatic(string $name = '', array $arguments = [])
    {
        if ($name === 'getFactory') {
            return self::doGetFactory();
        }

        throw new RuntimeException(sprintf("Method unknown: '%s'", $name));
    }

    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name = '', array $arguments = [])
    {
        if ($name === 'getFactory') {
            return self::doGetFactory();
        }

        throw new RuntimeException(sprintf("Method unknown: '%s'", $name));
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
