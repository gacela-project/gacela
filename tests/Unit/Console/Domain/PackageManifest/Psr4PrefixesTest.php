<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\PackageManifest;

use Gacela\Console\Domain\PackageManifest\Psr4Prefixes;
use PHPUnit\Framework\TestCase;

final class Psr4PrefixesTest extends TestCase
{
    public function test_nothing_matches_an_empty_map(): void
    {
        self::assertNull(Psr4Prefixes::longestMatching([], 'App\Order'));
    }

    public function test_a_class_no_prefix_covers_matches_nothing(): void
    {
        self::assertNull(Psr4Prefixes::longestMatching(['App\\' => 1], 'Other\Order'));
    }

    public function test_the_matching_prefix_is_returned(): void
    {
        self::assertSame('App\\', Psr4Prefixes::longestMatching(['App\\' => 1], 'App\Order'));
    }

    /**
     * The decision this exists for. Under first-match a project publishing both
     * `Gacela\` and `Gacela\LaravelBridge\` gets the wrong answer, whichever
     * order the map happens to be in.
     */
    public function test_the_longest_prefix_wins_whichever_order_they_are_declared(): void
    {
        $shortFirst = ['Gacela\\' => 1, 'Gacela\LaravelBridge\\' => 2];
        $longFirst = ['Gacela\LaravelBridge\\' => 2, 'Gacela\\' => 1];

        self::assertSame('Gacela\LaravelBridge\\', Psr4Prefixes::longestMatching($shortFirst, 'Gacela\LaravelBridge\Thing'));
        self::assertSame('Gacela\LaravelBridge\\', Psr4Prefixes::longestMatching($longFirst, 'Gacela\LaravelBridge\Thing'));
    }

    public function test_a_sibling_namespace_falls_back_to_the_shorter_prefix(): void
    {
        $prefixes = ['Gacela\\' => 1, 'Gacela\LaravelBridge\\' => 2];

        self::assertSame('Gacela\\', Psr4Prefixes::longestMatching($prefixes, 'Gacela\Framework\Thing'));
    }

    /**
     * An empty prefix claims everything, which is what composer's fallback
     * directory means -- it must still lose to any prefix that matches.
     */
    public function test_an_empty_prefix_matches_but_never_beats_a_real_one(): void
    {
        self::assertSame('', Psr4Prefixes::longestMatching(['' => 1], 'Anything\At\All'));
        self::assertSame('App\\', Psr4Prefixes::longestMatching(['' => 1, 'App\\' => 2], 'App\Order'));
    }
}
