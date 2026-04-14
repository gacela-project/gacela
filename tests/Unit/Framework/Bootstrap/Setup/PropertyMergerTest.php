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
