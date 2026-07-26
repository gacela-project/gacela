<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config;

use Gacela\Framework\Cache\WritableDirectory;
use Gacela\Framework\Config\MergedConfigCache;
use PHPUnit\Framework\TestCase;

use function glob;
use function is_dir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Two applications sharing one cache directory must not read each other's
 * merged config.
 *
 * The cache directory defaults to the system temp dir, which every app on the
 * machine shares. Before the filename was scoped by app root, they all read and
 * wrote `gacela-merged-config.php` — so whichever app bootstrapped first won,
 * and the others silently served its configuration. Silently is the operative
 * word: nothing failed, the values were simply another app's.
 *
 * `MergedConfigCache` fixes this by embedding a hash of the app root in the
 * filename, but no test held that property: every existing test constructs the
 * cache with at most `(dir, env)` and never exercises the third argument.
 * Written while inventorying the cache bugs for #539.
 */
final class SharedCacheDirIsolationTest extends TestCase
{
    private string $sharedCacheDir;

    protected function setUp(): void
    {
        $this->sharedCacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-shared-cache-' . uniqid('', true);
        WritableDirectory::resetCache();
    }

    protected function tearDown(): void
    {
        WritableDirectory::resetCache();
        $this->removeSharedCacheDir();
    }

    public function test_one_app_does_not_read_another_apps_merged_config(): void
    {
        $appA = new MergedConfigCache($this->sharedCacheDir, '', '/srv/app-a');
        $appB = new MergedConfigCache($this->sharedCacheDir, '', '/srv/app-b');

        $appA->write(['database-dsn' => 'app-a-database']);

        self::assertFalse(
            $appB->exists(),
            'app B must not find a cache just because app A wrote one to the shared directory',
        );

        $appB->write(['database-dsn' => 'app-b-database']);

        self::assertSame(['database-dsn' => 'app-a-database'], $appA->load());
        self::assertSame(['database-dsn' => 'app-b-database'], $appB->load());
    }

    /**
     * The app suffix and the env suffix have to compose, or two apps would
     * collide again as soon as either set APP_ENV.
     */
    public function test_app_scoping_composes_with_env_scoping(): void
    {
        $appAProd = new MergedConfigCache($this->sharedCacheDir, 'prod', '/srv/app-a');
        $appBProd = new MergedConfigCache($this->sharedCacheDir, 'prod', '/srv/app-b');
        $appADev = new MergedConfigCache($this->sharedCacheDir, 'dev', '/srv/app-a');

        $appAProd->write(['env' => 'a-prod']);
        $appBProd->write(['env' => 'b-prod']);
        $appADev->write(['env' => 'a-dev']);

        self::assertSame(['env' => 'a-prod'], $appAProd->load());
        self::assertSame(['env' => 'b-prod'], $appBProd->load());
        self::assertSame(['env' => 'a-dev'], $appADev->load());
    }

    /**
     * A cache written before filenames were app-scoped has no suffix, so it is
     * not addressed by any app any more. Clearing has to remove it too —
     * otherwise upgrading leaves an unreachable file in a shared temp dir that
     * no `cache:clear` can ever reap.
     */
    public function test_clear_also_removes_a_cache_written_before_filenames_were_app_scoped(): void
    {
        $legacy = new MergedConfigCache($this->sharedCacheDir);
        $legacy->write(['from' => 'before the app suffix existed']);
        self::assertTrue($legacy->exists());

        (new MergedConfigCache($this->sharedCacheDir, '', '/srv/app-a'))->clear();

        self::assertFalse($legacy->exists());
    }

    private function removeSharedCacheDir(): void
    {
        if (!is_dir($this->sharedCacheDir)) {
            return;
        }

        foreach (glob($this->sharedCacheDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->sharedCacheDir);
    }
}
