<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\ConfigFactory;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function getcwd;
use function sys_get_temp_dir;

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });
    }

    public function test_get_undefined_key(): void
    {
        $this->expectExceptionMessageMatches('/Could not find config key "undefined-key"/');
        Config::getInstance()->get('undefined-key');
    }

    public function test_get_default_value_from_undefined_key(): void
    {
        self::assertSame('default', Config::getInstance()->get('undefined-key', 'default'));
    }

    public function test_null_as_default_value_from_undefined_key(): void
    {
        self::assertNull(Config::getInstance()->get('undefined-key', null));
    }

    public function test_normalize_app_root_dir(): void
    {
        $config = Config::getInstance();
        $config->setAppRootDir(DIRECTORY_SEPARATOR . 'directory1');
        self::assertSame(DIRECTORY_SEPARATOR . 'directory1', $config->getAppRootDir());

        $config->setAppRootDir(DIRECTORY_SEPARATOR . 'directory2' . DIRECTORY_SEPARATOR);
        self::assertSame(DIRECTORY_SEPARATOR . 'directory2', $config->getAppRootDir());
    }

    public function test_get_instance_throws_when_not_bootstrapped(): void
    {
        Config::resetInstance();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bootstrap Gacela');

        Config::getInstance();
    }

    public function test_get_returns_value_set_via_app_config_key_value(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
            $config->addAppConfigKeyValue('my_config_key', 'my_config_value');
        });

        self::assertSame('my_config_value', Config::getInstance()->get('my_config_key'));
    }

    public function test_set_app_root_dir_falls_back_to_cwd_when_given_empty_string(): void
    {
        $config = Config::getInstance();

        $config->setAppRootDir('');

        self::assertSame(getcwd(), $config->getAppRootDir());
    }

    public function test_set_app_root_dir_falls_back_to_cwd_when_given_zero_string(): void
    {
        $config = Config::getInstance();

        $config->setAppRootDir('0');

        self::assertSame(getcwd(), $config->getAppRootDir());
    }

    public function test_get_cache_dir_strips_trailing_directory_separator(): void
    {
        // Bootstrap eagerly calls init() which memoises the cache dir, so we
        // drive Config directly here to observe the rtrim path on the first
        // and only call.
        Config::resetInstance();
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->setFileCache(true, sys_get_temp_dir() . '/trailing-sep/'),
        );
        $config = Config::createWithSetup($setup);
        $config->setAppRootDir(sys_get_temp_dir());

        self::assertSame(sys_get_temp_dir() . '/trailing-sep', $config->getCacheDir());
    }

    public function test_get_cache_dir_leaves_absolute_path_inside_app_root_unchanged(): void
    {
        $appRoot = Config::getInstance()->getAppRootDir();

        Gacela::bootstrap($appRoot, static function (GacelaConfig $config) use ($appRoot): void {
            $config->setFileCache(true, $appRoot . DIRECTORY_SEPARATOR . 'custom-cache');
        });

        self::assertSame(
            $appRoot . DIRECTORY_SEPARATOR . 'custom-cache',
            Config::getInstance()->getCacheDir(),
        );
    }

    public function test_get_cache_dir_prefixes_relative_path_with_app_root_and_separator(): void
    {
        $appRoot = Config::getInstance()->getAppRootDir();

        Gacela::bootstrap($appRoot, static function (GacelaConfig $config): void {
            $config->setFileCache(true, 'relative/cache/path');
        });

        self::assertSame(
            $appRoot . DIRECTORY_SEPARATOR . 'relative/cache/path',
            Config::getInstance()->getCacheDir(),
        );
    }

    public function test_get_cache_dir_strips_leading_separator_before_joining_relative_path(): void
    {
        // An input like `subdir/` with no absolute prefix is treated as relative,
        // concatenated as `$appRoot . DIRECTORY_SEPARATOR . ltrim(input, DS)`.
        $appRoot = Config::getInstance()->getAppRootDir();

        Gacela::bootstrap($appRoot, static function (GacelaConfig $config): void {
            $config->setFileCache(true, 'subdir');
        });

        self::assertSame(
            $appRoot . DIRECTORY_SEPARATOR . 'subdir',
            Config::getInstance()->getCacheDir(),
        );
    }

    public function test_get_factory_returns_cached_instance_across_calls(): void
    {
        $config = Config::getInstance();

        $factoryA = $config->getFactory();
        $factoryB = $config->getFactory();

        self::assertInstanceOf(ConfigFactory::class, $factoryA);
        self::assertSame($factoryA, $factoryB);
    }
}
