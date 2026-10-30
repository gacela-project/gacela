<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\ConfigFactory;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LiteralGlobPathsTest extends TestCase
{
    private string $root;

    private string|false $previousEnv;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-[v1]{eu,us}-' . uniqid('', true);
        mkdir($this->root . DIRECTORY_SEPARATOR . 'config', 0777, true);
        file_put_contents($this->root . '/config/app.php', "<?php return ['base' => true, 'value' => 'base'];");
        file_put_contents($this->root . '/config/app-prod.php', "<?php return ['env' => true, 'value' => 'prod'];");
        file_put_contents($this->root . '/local.php', "<?php return ['local' => true, 'value' => 'local'];");
        $this->previousEnv = getenv('APP_ENV');
        putenv('APP_ENV=prod');
    }

    protected function tearDown(): void
    {
        putenv($this->previousEnv === false ? 'APP_ENV' : 'APP_ENV=' . $this->previousEnv);
        Config::resetInstance();
        DirectoryUtil::removeDir($this->root);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function patterns(): iterable
    {
        yield 'wildcard' => ['config/*.php'];
        yield 'brace alternatives' => ['config/{app,other}.php'];
        yield 'nested brace alternatives' => ['config/{other,{app,another}}.php'];
    }

    #[DataProvider('patterns')]
    public function test_bootstrap_loads_every_layer_and_watches_real_paths(string $pattern): void
    {
        Gacela::bootstrap($this->root, static function (GacelaConfig $config) use ($pattern): void {
            $config->resetInMemoryCache()->setFileCache(false);
            $config->addAppConfig($pattern, 'local.php');
            $config->addConfigCacheWatch('missing.php');
        });
        $config = Config::getInstance();
        self::assertSame(['base' => true, 'value' => 'local', 'env' => true, 'local' => true], $config->getAllValues());
        $loader = (new ConfigFactory($this->root, $config->getSetupGacela()))->createConfigLoader();
        $watched = array_map(static fn (string $path): string => str_replace('\\', '/', $path), $loader->watchedPaths());
        $root = str_replace('\\', '/', $this->root);

        self::assertCount(3, $loader->sourceFiles());
        self::assertContains($root . '/config', $watched);
        self::assertContains($root . '/missing.php', $watched);
        self::assertContains($root, $watched);
    }
}
