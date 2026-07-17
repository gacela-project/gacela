<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm;

use Gacela\Console\Application\CacheWarm\CacheWarmOutputFormatter;
use Gacela\Console\Application\CacheWarm\CacheWarmService;
use Gacela\Console\Application\CacheWarm\ModuleWarmer;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Console\CacheWarm\FacadeWarm\Domain\Broken\BrokenFacade;
use GacelaTest\Integration\Console\CacheWarm\FacadeWarm\Domain\Healthy\HealthyFacade;
use GacelaTest\Integration\Console\CacheWarm\FacadeWarm\Domain\Healthy\HealthyFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function array_values;
use function implode;
use function is_dir;
use function rmdir;
use function uniqid;
use function unlink;

final class ModuleWarmerFacadeWarmTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '.gacela-cache-' . uniqid('', true);

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->resetInMemoryCache();
            $config->setFileCache(true, $cacheDir);
            $config->setProjectNamespaces(['GacelaTest\\Integration\\Console\\CacheWarm\\FacadeWarm\\Domain']);
        });

        ClassNamePhpCache::clearStaticCache();
    }

    protected function tearDown(): void
    {
        ClassNamePhpCache::clearStaticCache();
        $this->removeCacheDir();
    }

    public function test_a_php_error_warming_one_module_facade_does_not_abort_the_remaining_modules(): void
    {
        $warmer = new ModuleWarmer(
            new CacheWarmService(new ConsoleFacade()),
            new CacheWarmOutputFormatter($output = new BufferedOutput()),
        );

        // Broken first: before the fix its Error aborts the loop and Healthy is never warmed.
        $broken = new AppModule('Broken', 'Broken', BrokenFacade::class);
        $healthy = new AppModule('Healthy', 'Healthy', HealthyFacade::class, HealthyFactory::class);

        [, $skippedCount] = $warmer->warmModules([$broken, $healthy], warmAttributes: false);

        $resolved = implode('|', array_values(ClassNamePhpCache::all()));

        self::assertStringContainsString('HealthyFactory', $resolved, 'warming must continue past the broken module');
        self::assertGreaterThanOrEqual(1, $skippedCount, 'the broken module must be counted as skipped');
        self::assertStringContainsString('Broken', $output->fetch(), 'the failed module must be reported');
    }

    private function removeCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        foreach (glob($this->cacheDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->cacheDir);
    }
}
