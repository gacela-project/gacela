<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config\NumericKeys;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Exception\ConfigException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

/**
 * PHP stores a key such as '404' as an int. Merged by position, it was
 * renumbered: an environment file appended instead of overriding it.
 */
final class NumericKeysTest extends TestCase
{
    private string|false $appEnv;

    protected function setUp(): void
    {
        $this->appEnv = getenv('APP_ENV');
        putenv('APP_ENV=prod');

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->addAppConfig('config/*.php');
            $config->addAppConfigKeyValue('500', 'errors/server.html');
        });
    }

    protected function tearDown(): void
    {
        putenv($this->appEnv === false ? 'APP_ENV' : 'APP_ENV=' . $this->appEnv);
    }

    public function test_an_environment_file_overrides_a_numeric_key(): void
    {
        self::assertSame('errors/prod-not-found.html', Config::getInstance()->get('404'));
    }

    public function test_numeric_keys_from_every_source_keep_their_names(): void
    {
        self::assertSame('legacy', Config::getInstance()->get('2024'));
        self::assertSame('errors/server.html', Config::getInstance()->get('500'));
    }

    public function test_a_missing_key_is_reported_as_missing(): void
    {
        $this->expectException(ConfigException::class);

        Config::getInstance()->getString('not-there');
    }
}
