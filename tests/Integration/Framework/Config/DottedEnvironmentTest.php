<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DottedEnvironmentTest extends TestCase
{
    private string $rootDir;

    private string|false $appEnv;

    protected function setUp(): void
    {
        $this->appEnv = getenv('APP_ENV');
        $this->rootDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-dotted-env-' . bin2hex(random_bytes(4));
        mkdir($this->rootDir);
        file_put_contents($this->rootDir . DIRECTORY_SEPARATOR . 'app.php', "<?php return ['currency' => 'EUR'];");
        file_put_contents($this->rootDir . DIRECTORY_SEPARATOR . 'shared.php', "<?php return ['shared' => true];");
        file_put_contents($this->rootDir . DIRECTORY_SEPARATOR . 'app-prod.v2.php', "<?php return ['production' => true];");
    }

    protected function tearDown(): void
    {
        putenv($this->appEnv === false ? 'APP_ENV' : 'APP_ENV=' . $this->appEnv);
        Gacela::resetCache();
        DirectoryUtil::removeDir($this->rootDir);
    }

    #[DataProvider('environments')]
    public function test_only_the_matching_environment_loads_the_dotted_layer(?string $environment, ?bool $production): void
    {
        putenv($environment === null ? 'APP_ENV' : 'APP_ENV=' . $environment);
        Gacela::bootstrap($this->rootDir, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->addAppConfig('*.php');
        });

        $config = Config::getInstance();
        self::assertSame($production, $config->get('production', null));
        self::assertSame('EUR', $config->get('currency'));
        self::assertTrue($config->get('shared'));
    }

    /**
     * @return iterable<string, array{?string, ?bool}>
     */
    public static function environments(): iterable
    {
        yield 'unset' => [null, null];
        yield 'different environment' => ['dev', null];
        yield 'matching dotted environment' => ['prod.v2', true];
    }
}
