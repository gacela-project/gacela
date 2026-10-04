<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FileCache;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\SharedPlanCache;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\FileCache\Module\Persistence\FakeRepository;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

/**
 * Under PHP-FPM every request is a new process. With the file cache on, the
 * container plans one request made are where the next one starts.
 */
final class ContainerPlansFeatureTest extends TestCase
{
    private const CACHE_DIR = __DIR__ . '/plans-cache';

    protected function setUp(): void
    {
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    protected function tearDown(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });

        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    public function test_the_next_process_starts_with_the_plans_this_one_made(): void
    {
        $this->bootstrapWithFileCache();
        (new Module\Facade())->getName();
        $planned = SharedPlanCache::getInstance()->classes();

        SharedPlanCache::writeIfGrown();
        self::assertFileExists($this->plansFile());

        // What a new process sees: nothing in memory, the file on disk.
        $this->bootstrapWithFileCache();

        self::assertNotSame([], $planned);
        self::assertEqualsCanonicalizing($planned, SharedPlanCache::getInstance()->classes());
    }

    public function test_a_process_that_planned_nothing_new_does_not_write(): void
    {
        $this->bootstrapWithFileCache();
        SharedPlanCache::writeIfGrown();

        self::assertFileDoesNotExist($this->plansFile());
    }

    public function test_a_worker_bootstrapping_again_still_writes_what_it_planned_before(): void
    {
        $this->bootstrapWithFileCache();
        (new Module\Facade())->getName();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->enableFileCache('/plans-cache');
        });
        SharedPlanCache::writeIfGrown();

        self::assertFileExists($this->plansFile());
    }

    public function test_gacela_php_turns_it_on_when_the_closure_resets_first(): void
    {
        $root = __DIR__ . '/PlansEnabledInGacelaFile';
        $cacheDir = $root . '/plans-cache';

        try {
            Gacela::bootstrap($root, static function (GacelaConfig $config): void {
                $config->resetInMemoryCache();
            });
            (new Container())->get(FakeRepository::class);
            SharedPlanCache::writeIfGrown();

            self::assertFileExists(AbstractPhpFileCache::absoluteFilename($cacheDir, 'gacela-container-plans.php', $root));
        } finally {
            DirectoryUtil::removeDir($cacheDir);
        }
    }

    public function test_without_the_file_cache_nothing_is_written(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false, '/plans-cache');
        });
        (new Module\Facade())->getName();

        SharedPlanCache::writeIfGrown();

        self::assertFileDoesNotExist($this->plansFile());
    }

    private function bootstrapWithFileCache(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache('/plans-cache');
        });
    }

    private function plansFile(): string
    {
        return AbstractPhpFileCache::absoluteFilename(self::CACHE_DIR, 'gacela-container-plans.php', __DIR__);
    }
}
