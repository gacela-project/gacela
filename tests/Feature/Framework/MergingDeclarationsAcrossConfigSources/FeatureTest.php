<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MergingDeclarationsAcrossConfigSources;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * A bootstrap closure and `gacela.php` are two config sources, and the closure's
 * setup is the one the application ends up with -- so whatever the file declares
 * has to be merged onto it, or it is silently absent.
 *
 * Each of these was carried by a `SetupMerger` line that could be deleted with
 * the whole suite still green: the declarations survived by luck of nobody
 * looking, on three features whose entire point is being declared in the file
 * the merge reads.
 */
final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_a_config_dimension_declared_in_the_file_survives_the_merge(): void
    {
        self::assertSame(
            ['GACELA_TEST_REGION'],
            Config::getInstance()->getSetupGacela()->getConfigDimensions(),
        );
    }

    public function test_a_dto_shape_declared_in_the_file_survives_the_merge(): void
    {
        self::assertSame(
            ['AcmeMerge\Order'],
            array_keys(Config::getInstance()->getSetupGacela()->getDtoSchema()),
        );
    }

    public function test_a_config_schema_declared_in_the_file_survives_the_merge(): void
    {
        self::assertSame(
            ['shop.currency'],
            array_keys(Config::getInstance()->getSetupGacela()->getConfigSchema()),
        );
    }

    /**
     * The values the schema describes still load: a schema that survived while
     * the configuration it validates did not would pass the assertions above
     * and mean nothing.
     */
    public function test_the_configuration_the_schema_describes_is_loaded(): void
    {
        self::assertSame('EUR', Config::getInstance()->get('shop')['currency']);
    }
}
