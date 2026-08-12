<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ConfigDimensions;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Exception\ConfigDimensionException;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

/**
 * One application, one config directory, two regions.
 */
final class FeatureTest extends TestCase
{
    private const CACHE_DIR = __DIR__ . '/cache';

    public static function tearDownAfterClass(): void
    {
        Gacela::resetCache();
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    protected function tearDown(): void
    {
        putenv('APP_ENV');
        putenv('APP_REGION');
        Gacela::resetCache();
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    public function test_each_layer_refines_the_one_before_it(): void
    {
        putenv('APP_ENV=prod');
        putenv('APP_REGION=eu');
        $this->bootstrap();

        $config = Config::getInstance();

        self::assertSame('prod-eu', $config->get('layer'), 'the most specific layer wins');
        self::assertSame('kept', $config->get('prod-only'), 'the env layer still contributes');
        self::assertSame('kept', $config->get('base-only'), 'and so does the base');
    }

    public function test_a_different_region_reads_its_own_layer(): void
    {
        putenv('APP_ENV=prod');
        putenv('APP_REGION=us');
        $this->bootstrap();

        self::assertSame('prod-us', Config::getInstance()->get('layer'));
    }

    /**
     * An unset dimension ends the chain rather than leaving a hole in it, so
     * this reads the env layer and stops.
     */
    public function test_an_unset_dimension_ends_the_chain(): void
    {
        putenv('APP_ENV=prod');
        $this->bootstrap();

        self::assertSame('prod', Config::getInstance()->get('layer'));
    }

    /**
     * The test #465 and #597 would have wanted: two regions, one cache
     * directory, file cache on. Whichever boots first must not answer for the
     * other.
     */
    public function test_two_regions_never_share_a_warm_cache(): void
    {
        putenv('APP_ENV=prod');
        putenv('APP_REGION=eu');
        $this->bootstrap(fileCache: true);
        self::assertSame('prod-eu', Config::getInstance()->get('layer'));

        Gacela::resetCache();

        putenv('APP_REGION=us');
        $this->bootstrap(fileCache: true);
        self::assertSame('prod-us', Config::getInstance()->get('layer'));
    }

    /**
     * A dimension reaches a glob pattern and a filename, so a value that could
     * walk out of the config directory is refused where it is cheap to fix.
     */
    public function test_a_value_that_could_escape_the_config_directory_is_refused(): void
    {
        putenv('APP_ENV=prod');
        putenv('APP_REGION=../../etc');

        $this->expectException(ConfigDimensionException::class);
        $this->expectExceptionMessage('APP_REGION');

        $this->bootstrap();
        Config::getInstance()->get('layer');
    }

    private function bootstrap(bool $fileCache = false): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($fileCache): void {
            $config->resetInMemoryCache();
            $config->setFileCache($fileCache, self::CACHE_DIR);
            $config->addAppConfig('config/*.php');
            $config->addConfigDimension('APP_REGION');
        });
    }
}
