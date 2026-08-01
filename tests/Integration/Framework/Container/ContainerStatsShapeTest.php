<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Container\ContainerStats;
use Gacela\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_map;
use function implode;
use function sprintf;

/**
 * `debug:container` used to index six keys of the array returned by
 * `getStats()`, whose shape upstream explicitly carves out of its backward
 * compatibility promise: *"Do not build logic on it."* This test existed to
 * move the resulting breakage onto Gacela's CI, on the upgrade commit, instead
 * of into a user's terminal as an undefined-array-key error.
 *
 * Container 1.2 removed the need for that trade. `stats()` returns a
 * `final readonly ContainerStats` whose properties **are** covered, so the
 * command now reads typed properties and a rename fails the build at analysis
 * time rather than at runtime.
 *
 * What is left worth pinning is narrower, and it is not the shape: it is that
 * Gacela's own `Container` still forwards `stats()` at all. `stats()` lives on
 * the concrete `Container` and not on `ContainerInterface`, so unlike every
 * other method here it is not held in place by the interface -- which is the
 * mechanism the forwarding relies on everywhere else.
 */
final class ContainerStatsShapeTest extends TestCase
{
    public function test_the_typed_stats_are_forwarded_from_the_inner_container(): void
    {
        $container = new Container();
        $container->set('a-service', static fn (): string => 'value');

        $stats = $container->stats();

        self::assertInstanceOf(ContainerStats::class, $stats);
        self::assertSame(1, $stats->registeredServices);
    }

    /**
     * `ContainerInterface` does not declare `stats()`, so nothing makes the
     * delegation fail to compile if upstream adds a property to
     * `ContainerStats` and the command starts reading it. Naming the properties
     * the command relies on keeps that failure in CI.
     */
    public function test_every_property_debug_container_reads_still_exists(): void
    {
        $stats = (new Container())->stats();

        $missing = array_diff(
            ['registeredServices', 'frozenServices', 'factoryServices', 'bindings', 'cachedDependencies'],
            array_map(strval(...), array_keys(get_object_vars($stats))),
        );

        self::assertSame([], $missing, sprintf(
            'debug:container reads these properties, and ContainerStats no longer has them: %s',
            implode(', ', $missing),
        ));

        // Renamed in container 2.0: it always reported the whole PHP process,
        // never this container's footprint, and `memoryUsage` read as the latter.
        self::assertNotSame('', $stats->processMemoryFormatted());
    }
}
