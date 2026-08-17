<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Setup;

use Fixtures\CustomGacelaConfig;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\Setup\PropertyMerger;
use Gacela\Framework\Bootstrap\SetupGacela;
use PHPUnit\Framework\TestCase;

final class PropertyMergerTest extends TestCase
{
    public function test_merge_gacela_configs_to_extend_combines_current_and_list(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->extendGacelaConfig(CustomGacelaConfig::class),
        );

        $merger = new PropertyMerger($setup);
        $merger->mergeGacelaConfigsToExtend([AnotherGacelaConfigFixture::class]);

        self::assertSame(
            [CustomGacelaConfig::class, AnotherGacelaConfigFixture::class],
            $setup->getGacelaConfigsToExtend(),
        );
    }

    public function test_merge_gacela_configs_to_extend_deduplicates_existing_entries(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->extendGacelaConfig(CustomGacelaConfig::class),
        );

        $merger = new PropertyMerger($setup);
        $merger->mergeGacelaConfigsToExtend([CustomGacelaConfig::class, AnotherGacelaConfigFixture::class]);

        self::assertSame(
            [CustomGacelaConfig::class, AnotherGacelaConfigFixture::class],
            $setup->getGacelaConfigsToExtend(),
        );
    }

    /**
     * Accumulated, deduplicated, and still a list.
     *
     * Two sources refusing the same package are agreeing rather than refusing it
     * twice, and what comes out is read back by `doctor` and `debug:container`:
     * a repeated name reads there as a mistake, and the holes `array_unique()`
     * leaves in the keys are not the list the callers are handed.
     */
    public function test_merge_dont_discover_accumulates_without_repeating_a_name(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->dontDiscover(['acme/legacy', 'acme/older']),
        );

        $merger = new PropertyMerger($setup);
        $merger->mergeDontDiscover(['acme/legacy', 'acme/newer']);

        self::assertSame(['acme/legacy', 'acme/older', 'acme/newer'], $setup->getDontDiscover());
    }

    public function test_merge_gacela_configs_to_extend_keeps_existing_when_empty_list_passed(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->extendGacelaConfig(CustomGacelaConfig::class),
        );

        $merger = new PropertyMerger($setup);
        $merger->mergeGacelaConfigsToExtend([]);

        self::assertSame([CustomGacelaConfig::class], $setup->getGacelaConfigsToExtend());
    }
}

class AnotherGacelaConfigFixture
{
}
