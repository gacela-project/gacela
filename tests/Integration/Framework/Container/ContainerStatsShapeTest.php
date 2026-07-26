<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Pins the `getStats()` keys that `debug:container` reads.
 *
 * Upstream states the return shape of `getStats()` is **not** covered by its
 * backward-compatibility policy: *"Do not build logic on it."* Gacela does
 * build on it -- `ConsoleFactory::getContainerStats()` hands it to
 * `DebugContainerCommand`, which indexes six keys directly.
 *
 * That is a defensible trade for a debug command, but it means a container
 * release may rename a key and break `debug:container` at runtime, in a user's
 * terminal, with an undefined-array-key error. This test moves that failure to
 * our CI on the upgrade commit instead, which is the whole point of depending
 * on an unstable shape deliberately rather than accidentally.
 *
 * If this fails after a container bump: fix `DebugContainerCommand` to read the
 * new shape, and update the list below.
 */
final class ContainerStatsShapeTest extends TestCase
{
    /**
     * Exactly the keys indexed in `DebugContainerCommand::execute()`.
     *
     * @var list<string>
     */
    private const array KEYS_DEBUG_CONTAINER_READS = [
        'bindings',
        'cached_dependencies',
        'factory_services',
        'frozen_services',
        'memory_usage',
        'registered_services',
    ];

    public function test_get_stats_still_provides_every_key_debug_container_reads(): void
    {
        $stats = (new Container())->getStats();

        foreach (self::KEYS_DEBUG_CONTAINER_READS as $key) {
            self::assertArrayHasKey(
                $key,
                $stats,
                "debug:container reads \$stats['{$key}']; the container no longer provides it",
            );
        }
    }

}
