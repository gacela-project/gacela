<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use Gacela\Container\Container as InnerContainer;
use Gacela\Framework\Container\Container as Decorator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

use function array_diff;
use function array_intersect;
use function array_map;
use function array_values;
use function implode;
use function in_array;
use function sort;
use function sprintf;

/**
 * The decorator's own docblock used to argue that implementing
 * `ContainerInterface` keeps the forwarding honest -- a method added upstream
 * would fail compilation here. That only holds for methods ON the interface,
 * and 1.x promises never to add one: every capability since 1.0 (`stats`,
 * `provides`, `createScope`, `taggedByKey`, `lazy`, the compiled-factory pair)
 * landed on the concrete class instead, where nothing notices it going
 * unforwarded. Two of them shipped unreachable before anyone did.
 *
 * So the guard is here rather than in the type system. A new upstream method
 * fails this test until it is either forwarded or listed below as a decision.
 */
final class ContainerForwardingCoverageTest extends TestCase
{
    /**
     * Deliberately not forwarded, each for a reason that outlives the version
     * that introduced it.
     */
    private const NOT_FORWARDED = [
        // Returns a child `Gacela\Container\Container`, which the decorator has
        // no way to wrap: $inner is readonly and assigned in the constructor.
        // Forwarding it raw would hand callers a container whose closures are
        // passed the inner instance, so `$c->getLocator()` -- the documented
        // provider signature -- would fatal. Needs a request-lifetime design
        // first; see the scopes discussion in the container docs.
        'createScope',
    ];

    /**
     * Statics are not inherited and need no forwarder: a caller reaches them on
     * `Gacela\Container\Container` directly, which is what Gacela itself does
     * for the compiled-cache readers.
     */
    private const STATICS = [
        'create',
        'loadCompiledCache',
        'loadCompiledFactories',
        'resetStaticCaches',
    ];

    public function test_every_public_container_method_is_forwarded_or_a_recorded_decision(): void
    {
        $unhandled = array_diff($this->innerPublicMethods(), $this->decoratorPublicMethods());

        self::assertSame([], $unhandled, sprintf(
            "Container %s exposes methods the decorator neither forwards nor declines:\n  %s\n"
            . 'Forward them in %s, or add them to self::NOT_FORWARDED with the reason.',
            InnerContainer::class,
            implode("\n  ", $unhandled),
            Decorator::class,
        ));
    }

    public function test_the_recorded_decisions_still_name_methods_that_exist(): void
    {
        $gone = array_diff(
            [...self::NOT_FORWARDED, ...self::STATICS],
            array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                (new ReflectionClass(InnerContainer::class))->getMethods(ReflectionMethod::IS_PUBLIC),
            ),
        );

        self::assertSame([], $gone, 'These are recorded as deliberate decisions but no longer exist upstream: '
            . implode(', ', $gone));
    }

    /**
     * The list above is an allow-list, so an entry that stops being true stops
     * guarding anything: the method is forwarded, and the record quietly excuses
     * it anyway. `load` and `loadFile` were listed here and forwarded in #578
     * without the list noticing, which is what this asserts against.
     */
    public function test_no_recorded_decision_is_actually_forwarded(): void
    {
        $forwarded = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(Decorator::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        $stale = array_intersect(self::NOT_FORWARDED, $forwarded);

        self::assertSame([], array_values($stale), 'These are recorded as deliberately not forwarded, but '
            . Decorator::class . ' forwards them — drop them from self::NOT_FORWARDED: ' . implode(', ', $stale));
    }

    /**
     * @return list<string>
     */
    private function innerPublicMethods(): array
    {
        $names = [];

        foreach ((new ReflectionClass(InnerContainer::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if ($method->isStatic()) {
                continue;
            }
            if ($method->isConstructor()) {
                continue;
            }

            if (in_array($name, self::NOT_FORWARDED, true)) {
                continue;
            }

            $names[] = $name;
        }

        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function decoratorPublicMethods(): array
    {
        $names = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(Decorator::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        sort($names);

        return $names;
    }
}
