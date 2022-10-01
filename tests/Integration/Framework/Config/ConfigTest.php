<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setCacheEnabled(false);
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
        $config->setAppRootDir('/directory1');
        self::assertSame('/directory1', $config->getAppRootDir());

        $config->setAppRootDir('/directory2/');
        self::assertSame('/directory2', $config->getAppRootDir());
    }
}
