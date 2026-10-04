<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config\LayersAcrossConfigItems;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

/**
 * With two `addAppConfig()` items, the precedence still holds layer by layer:
 * an environment file beats every base file, and a local file beats them all.
 */
final class LayersAcrossConfigItemsTest extends TestCase
{
    private string|false $appEnv;

    protected function setUp(): void
    {
        $this->appEnv = getenv('APP_ENV');
        putenv('APP_ENV=prod');

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->addAppConfig('config/*.php', 'config/local.php');
            $config->addAppConfig('config/extra/*.php');
        });
    }

    protected function tearDown(): void
    {
        putenv($this->appEnv === false ? 'APP_ENV' : 'APP_ENV=' . $this->appEnv);
    }

    public function test_an_environment_file_beats_a_later_item_base_file(): void
    {
        self::assertSame('prod', Config::getInstance()->get('db'));
    }

    public function test_a_local_file_beats_a_later_item_base_file(): void
    {
        self::assertTrue(Config::getInstance()->get('debug'));
    }
}
