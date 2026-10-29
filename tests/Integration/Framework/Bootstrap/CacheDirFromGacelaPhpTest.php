<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap;

use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\Package\InstalledPackagesReader;
use Gacela\Framework\Bootstrap\Package\PackageConfigCache;
use Gacela\Framework\Bootstrap\Package\PackageConfigDeclaration;
use Gacela\Framework\Bootstrap\Package\PackageDiscoveryRegistry;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use stdClass;

use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

final class CacheDirFromGacelaPhpTest extends TestCase
{
    private string $root;

    private string|false $cacheDirEnvironment;

    private string|false $appEnvironment;

    protected function setUp(): void
    {
        $this->cacheDirEnvironment = getenv('GACELA_CACHE_DIR');
        $this->appEnvironment = getenv('APP_ENV');
        putenv('GACELA_CACHE_DIR');
        putenv('APP_ENV=test');
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-cache-dir-' . bin2hex(random_bytes(8));
        mkdir($this->root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer', 0777, true);
        file_put_contents($this->root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json', '{"packages":[],"dev":false,"dev-package-names":[]}');
        Gacela::resetCache();
        Config::resetInstance();
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
        putenv($this->cacheDirEnvironment === false ? 'GACELA_CACHE_DIR' : 'GACELA_CACHE_DIR=' . $this->cacheDirEnvironment);
        putenv($this->appEnvironment === false ? 'APP_ENV' : 'APP_ENV=' . $this->appEnvironment);
        DirectoryUtil::removeDir($this->root);
    }

    public function test_project_directory_is_used_before_package_discovery(): void
    {
        $this->writeConfig('gacela.php', 'var/cache');
        $this->bootstrap();

        $this->assertCacheDirectory('cache');
    }

    public function test_environment_directory_overrides_project_before_package_discovery(): void
    {
        $this->writeConfig('gacela.php', 'var/project');
        $this->writeConfig('gacela-test.php', 'var/environment');
        $this->bootstrap();

        $this->assertCacheDirectory('environment');
        self::assertDirectoryDoesNotExist($this->root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'project');
    }

    public function test_environment_directory_is_used_without_a_project_config_file(): void
    {
        $this->writeConfig('gacela-test.php', 'var/environment');
        $this->bootstrap();

        $this->assertCacheDirectory('environment');
    }

    public function test_project_can_disable_the_bootstrap_file_cache(): void
    {
        $this->writeSetup('gacela.php', '$config->setFileCache(false);');
        $this->bootstrap();

        self::assertFalse(Config::getInstance()->getSetupGacela()->isFileCacheEnabled());
        self::assertSame([], glob($this->root . DIRECTORY_SEPARATOR . 'gacela-discovered-packages*.php'));
    }

    public function test_project_directory_overrides_bootstrap_directory(): void
    {
        $this->writeConfig('gacela.php', 'var/project');
        Gacela::bootstrap($this->root, static function (GacelaConfig $config): void {
            $config->enableFileCache('var/bootstrap');
        });

        $this->assertCacheDirectory('project');
        self::assertDirectoryDoesNotExist($this->root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'bootstrap');
    }

    public function test_environment_config_reads_project_external_services_once(): void
    {
        $this->writeSetup('gacela.php', <<<'PHP_CONFIG'
    file_put_contents(__DIR__ . '/executions', 'project', FILE_APPEND);
    $config->addExternalService('service', new \stdClass());
PHP_CONFIG);
        $this->writeSetup('gacela-test.php', <<<'PHP_CONFIG'
    file_put_contents(__DIR__ . '/executions', 'env', FILE_APPEND);
    $config->addBinding('env-service', $config->getExternalService('service'));
    $config->enableFileCache('var/environment');
PHP_CONFIG);
        $this->bootstrap();

        $this->assertCacheDirectory('environment');
        self::assertInstanceOf(stdClass::class, Gacela::get('env-service'));
        self::assertSame('projectenv', file_get_contents($this->root . DIRECTORY_SEPARATOR . 'executions'));
    }

    public function test_environment_config_keeps_package_external_services(): void
    {
        $package = $this->writePackageSetup();
        $this->installPackage($package);
        $this->writeSetup('gacela-test.php', <<<'PHP_CONFIG'
    $config->addBinding('env-service', $config->getExternalService('service'));
    $config->enableFileCache('var/environment');
PHP_CONFIG);
        $this->bootstrap();

        $this->assertCacheDirectory('environment');
        self::assertInstanceOf(stdClass::class, Gacela::get('env-service'));
    }

    public function test_without_environment_config_the_warmed_declaration_cache_is_read(): void
    {
        $package = $this->writePackageSetup();
        $this->writeConfig('gacela.php', 'var/cache');
        $cache = new PackageConfigCache($this->root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache', $this->root);
        $fingerprint = (new InstalledPackagesReader($this->root))->fingerprint();
        self::assertNotNull($fingerprint);
        $cache->write($fingerprint, [new PackageConfigDeclaration('example/package', 'gacela.php', $package . DIRECTORY_SEPARATOR . 'gacela.php')]);

        $this->bootstrap();

        self::assertCount(1, PackageDiscoveryRegistry::discovered());
        self::assertInstanceOf(stdClass::class, Config::getInstance()->getSetupGacela()->externalServices()['service']);
    }

    public function test_environment_config_can_disable_caching_before_the_deferred_write(): void
    {
        $this->writeConfig('gacela.php', 'var/cache');
        $this->writeSetup('gacela-test.php', '$config->setFileCache(false);');
        $this->bootstrap();

        self::assertFalse(Config::getInstance()->getSetupGacela()->isFileCacheEnabled());
        self::assertDirectoryDoesNotExist($this->root . DIRECTORY_SEPARATOR . 'var');
    }

    public function test_environment_config_can_enable_caching_for_the_deferred_write(): void
    {
        $this->writeConfig('gacela-test.php', 'var/environment');
        Gacela::bootstrap($this->root, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });

        $this->assertCacheDirectory('environment');
    }

    public function test_cache_directory_environment_variable_keeps_precedence(): void
    {
        $this->writeConfig('gacela.php', 'var/project');
        putenv('GACELA_CACHE_DIR=' . $this->root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'override');
        $this->bootstrap();

        $this->assertCacheDirectory('override');
    }

    public function test_warming_without_a_closure_uses_the_application_cache_files(): void
    {
        $this->writeConfig('gacela.php', 'var/cache');
        Gacela::bootstrap($this->root);
        $command = new CommandTester(new CacheWarmCommand());
        self::assertSame(0, $command->execute([]));
        $cacheFile = Config::getInstance()->mergedConfigCacheFilename();
        self::assertFileExists($cacheFile);
        Gacela::resetCache();
        Config::resetInstance();

        $this->bootstrap();

        $this->assertCacheDirectory('cache');
        self::assertSame($cacheFile, Config::getInstance()->mergedConfigCacheFilename());
    }

    private function bootstrap(): void
    {
        Gacela::bootstrap($this->root, static function (GacelaConfig $config): void {
            $config->setFileCache(true);
        });
    }

    private function assertCacheDirectory(string $directory): void
    {
        $expected = $this->root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . $directory;
        self::assertSame($expected, Config::getInstance()->getCacheDir());
        self::assertFileExists((new PackageConfigCache($expected, $this->root))->path());
        self::assertFileDoesNotExist($expected . DIRECTORY_SEPARATOR . PackageConfigCache::FILENAME);
    }

    private function writePackageSetup(): string
    {
        $package = $this->root . DIRECTORY_SEPARATOR . 'package';
        mkdir($package);
        file_put_contents($package . DIRECTORY_SEPARATOR . 'gacela.php', <<<'PHP_CONFIG'
<?php
return static function (\Gacela\Framework\Bootstrap\GacelaConfig $config): void {
    $config->addExternalService('service', new \stdClass());
};
PHP_CONFIG);

        return $package;
    }

    private function installPackage(string $package): void
    {
        $manifest = ['packages' => [[
            'name' => 'example/package',
            'install-path' => $package,
            'extra' => ['gacela' => ['config' => 'gacela.php']],
        ]], 'dev' => false];
        file_put_contents($this->root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    private function writeConfig(string $filename, string $directory): void
    {
        $this->writeSetup($filename, sprintf('$config->enableFileCache(%s);', var_export($directory, true)));
    }

    private function writeSetup(string $filename, string $body): void
    {
        file_put_contents($this->root . DIRECTORY_SEPARATOR . $filename, sprintf(<<<'PHP_CONFIG'
<?php
use Gacela\Framework\Bootstrap\GacelaConfig;
return static function (GacelaConfig $config): void {
%s
};
PHP_CONFIG, $body));
    }
}
