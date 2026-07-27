<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\Cache\WritableDirectory;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function sys_get_temp_dir;
use function uniqid;

/**
 * Pins the in-memory half of the file-backed caches to `Gacela::resetCache()`.
 *
 * `clearStaticCache()` had existed since the batching work and was covered by
 * unit tests, but the only callers were those tests and a benchmark: the
 * central reset cannot name concrete subclasses, so nothing in production ever
 * reached it. With the file cache enabled, an entry read from disk therefore
 * outlived `Gacela::resetCache()` and `GacelaConfig::resetInMemoryCache()`
 * alike -- the same "a working reset that nothing calls" shape as the
 * ClassValidator and ReflectionClassPool cases, and invisible to
 * ResetCacheCoverageTest because the method is not named reset*.
 */
final class AbstractPhpFileCacheResetTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/gacela-cache-reset-' . uniqid('', true);
        AbstractPhpFileCache::resetCache();
        WritableDirectory::resetCache();
    }

    protected function tearDown(): void
    {
        AbstractPhpFileCache::resetCache();
        WritableDirectory::resetCache();
        DirectoryUtil::removeDir($this->cacheDir);
    }

    public function test_reset_cache_drops_entries_of_every_subclass(): void
    {
        (new TestPhpFileCache($this->cacheDir))->put('key', 'AnyClassName');
        (new ResetProbePhpFileCache($this->cacheDir))->put('other', 'AnotherClassName');

        Gacela::resetCache();

        self::assertSame([], TestPhpFileCache::all());
        self::assertSame([], ResetProbePhpFileCache::all());
    }

    public function test_a_cached_answer_does_not_survive_a_reset(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);
        $cache->put('key', 'AnyClassName');
        self::assertTrue($cache->has('key'));

        Gacela::resetCache();

        self::assertFalse(
            $cache->has('key'),
            'an instance that outlived the reset must not keep answering from the pre-reset cache',
        );
    }

    /**
     * The registry of absolute filenames is cleared with everything else, which
     * is only safe because `put()` re-registers what it writes. Without that,
     * an instance held across a reset -- and one always is, inside
     * ClassResolverCache -- would fail on its next write instead.
     */
    public function test_an_instance_can_still_write_after_its_filename_registry_was_cleared(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);

        Gacela::resetCache();

        $cache->put('written-after-reset', 'AnyClassName');

        self::assertSame(['written-after-reset' => 'AnyClassName'], TestPhpFileCache::all());
        self::assertFileExists($this->cacheDir . '/' . TestPhpFileCache::FILENAME);
    }

    /**
     * The batched path is where a cleared registry bites hardest, because it
     * fails silently rather than loudly: `commitBatch()` is static, so it has
     * no instance to ask for a filename and skips any class it cannot resolve.
     * A batch opened after a reset would mark the cache dirty and then be
     * dropped on commit, losing the writes with no error anywhere.
     */
    public function test_a_batch_opened_after_a_reset_is_still_flushed_to_disk(): void
    {
        $cache = new TestPhpFileCache($this->cacheDir);

        Gacela::resetCache();

        AbstractPhpFileCache::beginBatch();
        $cache->put('batched-after-reset', 'AnyClassName');
        AbstractPhpFileCache::commitBatch();

        $filename = $this->cacheDir . '/' . TestPhpFileCache::FILENAME;
        self::assertFileExists($filename);
        self::assertSame(['batched-after-reset' => 'AnyClassName'], require $filename);
    }

    public function test_reset_cache_cancels_an_open_batch(): void
    {
        AbstractPhpFileCache::beginBatch();

        Gacela::resetCache();

        self::assertFalse(AbstractPhpFileCache::isBatching());
    }

    /**
     * A batch left dirty by the reset would be flushed to disk by the next
     * unrelated commitBatch(), writing pre-reset entries back out.
     */
    public function test_reset_cache_discards_pending_batch_writes(): void
    {
        AbstractPhpFileCache::beginBatch();
        (new TestPhpFileCache($this->cacheDir))->put('key', 'AnyClassName');

        Gacela::resetCache();

        $dirty = new ReflectionProperty(AbstractPhpFileCache::class, 'dirty');

        self::assertSame([], $dirty->getValue());
    }
}

final class ResetProbePhpFileCache extends AbstractPhpFileCache
{
    protected function getCacheFilename(): string
    {
        return 'gacela-reset-probe.php';
    }
}
