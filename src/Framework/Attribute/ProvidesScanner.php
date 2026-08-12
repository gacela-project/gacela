<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Gacela\Framework\Container\Container;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Scans #[Provides] attributes on a provider and registers each annotated
 * method into the given Container as a lazy service factory.
 *
 * @internal
 *
 * @psalm-type ProvidesEntry = array{id: string, method: ReflectionMethod, needsContainer: bool}
 */
final class ProvidesScanner
{
    /** @var array<class-string, list<ProvidesEntry>> */
    private static array $cache = [];

    public static function scan(object $provider, Container $container): void
    {
        foreach (self::entriesFor(new ReflectionClass($provider)) as $entry) {
            $method = $entry['method'];

            $callback = $entry['needsContainer']
                ? static fn (Container $c): mixed => $method->invoke($provider, $c)
                : static fn (): mixed => $method->invoke($provider);

            $container->set($entry['id'], $callback);
        }
    }

    /**
     * Which services a provider class declares, without instantiating it.
     *
     * Public so that a reader which only has the class name -- the IDE metadata
     * scanner -- asks the same question the container asks, rather than
     * re-deriving "a public method carrying #[Provides]" beside it. Widening
     * that here would otherwise register services the generated metadata never
     * mentions.
     *
     * @param ReflectionClass<object> $reflection
     *
     * @return list<ProvidesEntry>
     */
    public static function entriesFor(ReflectionClass $reflection): array
    {
        $class = $reflection->getName();

        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        $entries = [];

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
