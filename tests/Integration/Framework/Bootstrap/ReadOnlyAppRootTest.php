<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\MergedConfigCache;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\ReadOnlyDirTrait;
use GacelaTest\Fixtures\WarningCollectorTrait;
use GacelaTest\Integration\Framework\Bootstrap\ReadOnlyAppRoot\Facade as GreeterFacade;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function chmod;
use function file_put_contents;
use function getenv;
use function is_file;
use function mkdir;
use function putenv;
use function sprintf;
use function var_export;

/**
 * The scenario that fataled Phel inside the NixOS build sandbox: a project
 * bootstrapped from a read-only root, with the file cache enabled and pointing
 * inside that root. Gacela must degrade to in-memory caching instead of
 * throwing during bootstrap.
 */
final class ReadOnlyAppRootTest extends TestCase
{
    use ReadOnlyDirTrait;
    use WarningCollectorTrait;

    private ?string $originalAppEnv = null;

    protected function setUp(): void
    {
        $env = getenv('APP_ENV');
        $this->originalAppEnv = $env === false ? null : $env;
        putenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        putenv('GACELA_CACHE_DIR');
        putenv($this->originalAppEnv === null ? 'APP_ENV' : 'APP_ENV=' . $this->originalAppEnv);
        $this->restoreReadOnlyDirs();
        Gacela::resetCache();
    }

    public function test_bootstrap_with_file_cache_degrades_gracefully_in_read_only_app_root(): void
    {
        $appRoot = $this->createReadOnlyDirOrSkip('ro-approot', static function (string $dir): void {
            mkdir($dir . '/config', 0o755, true);
            file_put_contents($dir . '/config/config.php', '<?php return ["ro_key" => "ro_value"];');
        });

        $warnings = $this->collectWarnings(static function () use ($appRoot): array {
            Gacela::bootstrap($appRoot, self::fileCacheConfigFn());

            return [
                Config::getInstance()->get('ro_key'),
                (new GreeterFacade())->greet(),
            ];
        }, $observed);

        self::assertSame([], $warnings);
        self::assertSame(['ro_value', 'greetings-from-read-only-root'], $observed);
        self::assertDirectoryDoesNotExist($appRoot . '/.gacela');
        self::assertFalse(is_file(Config::getInstance()->mergedConfigCacheFilename()));
    }

    public function test_pre_warmed_merged_config_is_read_from_read_only_cache_dir(): void
    {
        $appRoot = $this->createReadOnlyDirOrSkip('ro-prewarmed', static function (string $dir): void {
            mkdir($dir . '/config', 0o755, true);
            file_put_contents($dir . '/config/config.php', '<?php return ["ro_key" => "from_files"];');

            $cacheDir = $dir . '/.gacela/cache';
            mkdir($cacheDir, 0o755, true);
            file_put_contents(
                $cacheDir . '/' . MergedConfigCache::FILENAME_PREFIX . MergedConfigCache::FILENAME_EXTENSION,
                sprintf('<?php return %s;', var_export(['ro_key' => 'from_prewarmed_cache'], true)),
            );
            chmod($cacheDir, 0o555);
        });

        $warnings = $this->collectWarnings(static function () use ($appRoot): string {
            Gacela::bootstrap($appRoot, self::fileCacheConfigFn());

            /** @var string $value */
            $value = Config::getInstance()->get('ro_key');

            return $value;
        }, $observed);

        self::assertSame([], $warnings);
        self::assertSame('from_prewarmed_cache', $observed);
    }

    public function test_explicit_cache_dir_env_override_stays_loud_when_unusable(): void
    {
        $appRoot = $this->createReadOnlyDirOrSkip('ro-env-override', static function (string $dir): void {
            mkdir($dir . '/config', 0o755, true);
            file_put_contents($dir . '/config/config.php', '<?php return ["ro_key" => "ro_value"];');
        });

        putenv('GACELA_CACHE_DIR=' . $appRoot . '/.gacela/cache');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('was not created');

        // The merged-config auto-warm runs while bootstrapping.
        Gacela::bootstrap($appRoot, self::fileCacheConfigFn());
        Config::getInstance()->get('ro_key');
    }

    private static function fileCacheConfigFn(): Closure
    {
        return static function (GacelaConfig $config): void {
            $config->setFileCache(true, '.gacela/cache');
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php');
        };
    }
}
