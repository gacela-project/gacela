<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Gacela\Framework\Container\Container;
use Gacela\Framework\Exception\CircularProvidesException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use function array_pop;
use function array_slice;

/**
 * Scans #[Provides] attributes on a provider and registers each annotated
 * method into the given Container as a lazy service factory.
 *
 * @internal
 *
 * @psalm-type ProvidesEntry = array{id: string, method: ReflectionMethod, needsContainer: bool}
 * @psalm-type ProvidesFrame = array{id: string, provider: class-string, method: string}
 */
final class ProvidesScanner
{
    /** @var array<class-string, list<ProvidesEntry>> */
    private static array $cache = [];

    /**
     * The provided methods currently running, outermost first.
     *
     * Pushed and popped around every provider call, so between resolutions it
     * is empty -- state for the duration of one `get()`, not a cache.
     *
     * @var list<ProvidesFrame>
     */
    private static array $resolving = [];

    public static function scan(object $provider, Container $container): void
    {
        foreach (self::entriesFor(new ReflectionClass($provider)) as $entry) {
            $method = $entry['method'];
            $id = $entry['id'];

            $callback = $entry['needsContainer']
                ? static fn (Container $c): mixed => self::provide($provider, $method, $id, $c)
                : static fn (): mixed => self::provide($provider, $method, $id, null);

            $container->set($id, $callback);
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

    /**
     * One provider call, with the guard around it.
     *
     * Without it a self-referential declaration is a `get()` that calls the
     * method that calls `get()`, which on CLI ends in PHP's own
     * "Maximum call stack size ... Infinite recursion?" -- an Error carrying a
     * hundred thousand identical frames and naming neither the provider nor the
     * id. The declaration is accepted at registration and only the first
     * resolution finds out.
     */
    private static function provide(object $provider, ReflectionMethod $method, string $id, ?Container $container): mixed
    {
        $frame = ['id' => $id, 'provider' => $provider::class, 'method' => $method->getName()];

        self::guardAgainstCycle($frame);

        self::$resolving[] = $frame;

        try {
            return $container instanceof Container
                ? $method->invoke($provider, $container)
                : $method->invoke($provider);
        } finally {
            array_pop(self::$resolving);
        }
    }

    /**
     * Matched on the declaration rather than on the id alone.
     *
     * Two modules may legitimately provide the same id -- each answers for
     * itself, which is why `DuplicateProvidedIdCheck` looks per Provider -- and
     * one resolving the other's would trip a guard keyed on the id. A cycle
     * always comes back through the *same* declaration, so the declaration is
     * the thing to match.
     *
     * @param ProvidesFrame $frame
     */
    private static function guardAgainstCycle(array $frame): void
    {
        foreach (self::$resolving as $index => $running) {
            if ($running === $frame) {
                // The frame it came back to, then everything the loop went
                // through after it.
                throw CircularProvidesException::detected([$running, ...array_slice(self::$resolving, $index + 1)]);
            }
        }
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
