<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Scans #[Provides] attributes on a provider and registers each annotated
 * method into the given Container as a lazy service factory.
 *
 * @internal
 */
final class ProvidesScanner
{
    /** @var array<class-string<AbstractProvider>, list<array{id:string, method:ReflectionMethod, needsContainer:bool}>> */
    private static array $cache = [];

    public static function scan(AbstractProvider $provider, Container $container): void
    {
        foreach (self::resolveEntries($provider) as $entry) {
            $method = $entry['method'];

            $callback = $entry['needsContainer']
                ? static fn (Container $c): mixed => $method->invoke($provider, $c)
                : static fn (): mixed => $method->invoke($provider);

            $container->set($entry['id'], $callback);
        }
    }

    /**
     * @return list<array{id:string, method:ReflectionMethod, needsContainer:bool}>
     */
    private static function resolveEntries(AbstractProvider $provider): array
    {
        $class = $provider::class;

        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        $entries = [];
        $reflection = new ReflectionClass($provider);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Provides::class) as $attribute) {
                /** @var Provides $instance */
                $instance = $attribute->newInstance();

                $entries[] = [
                    'id' => $instance->id,
                    'method' => $method,
                    'needsContainer' => self::needsContainer($method),
                ];
            }
        }

        return self::$cache[$class] = $entries;
    }

    private static function needsContainer(ReflectionMethod $method): bool
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && $type->getName() === Container::class) {
                return true;
            }
        }

        return false;
    }
}
