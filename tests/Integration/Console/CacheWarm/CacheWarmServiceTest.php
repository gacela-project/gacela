<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm;

use Gacela\Console\Application\CacheWarm\CacheWarmService;
use Gacela\Console\ConsoleFacade;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Facade;
use PHPUnit\Framework\TestCase;

use function array_values;
use function implode;
use function rmdir;
use function uniqid;
use function unlink;

final class CacheWarmServiceTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '.gacela-cache-' . uniqid('', true);

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->resetInMemoryCache();
            $config->setFileCache(true, $cacheDir);
            $config->setProjectNamespaces(['GacelaTest\\Integration\\Console\\AllAppModules\\Domain']);
        });

        ClassNamePhpCache::clearStaticCache();
    }

    protected function tearDown(): void
    {
        ClassNamePhpCache::clearStaticCache();
        $this->removeCacheDir();
    }

    public function test_warm_class_resolution_populates_factory_config_and_provider_entries(): void
    {
        $service = new CacheWarmService(new ConsoleFacade());

        $service->warmClassResolution(Module1Facade::class);

        $resolved = implode('|', array_values(ClassNamePhpCache::all()));

        self::assertStringContainsString('Module1Factory', $resolved);
        self::assertStringContainsString('Module1Config', $resolved);
        self::assertStringContainsString('Module1Provider', $resolved);
    }

    public function test_warm_class_resolution_is_idempotent(): void
    {
        $service = new CacheWarmService(new ConsoleFacade());

        $service->warmClassResolution(Module1Facade::class);

        $firstRun = ClassNamePhpCache::all();

        $service->warmClassResolution(Module1Facade::class);
        $secondRun = ClassNamePhpCache::all();

        self::assertSame($firstRun, $secondRun);
    }

    public function test_warm_class_resolution_skips_when_facade_class_missing(): void
    {
        $service = new CacheWarmService(new ConsoleFacade());

        /** @var class-string $fake */
        $fake = 'Non\\Existing\\MissingFacade';
        $service->warmClassResolution($fake);

        self::assertSame([], ClassNamePhpCache::all());
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
