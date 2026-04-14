<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache;

use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;

use function bin2hex;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * @BeforeMethods({"setUp"})
 *
 * @AfterMethods({"tearDown"})
 *
 * @Revs(50)
 *
 * @Iterations(5)
 */
final class BatchWriteBench
{
    private string $cacheDir;

    public function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/gacela-bench-' . bin2hex(random_bytes(4));
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        BatchBenchCache::clearStaticCache();
    }

    public function tearDown(): void
    {
        $file = $this->cacheDir . '/' . BatchBenchCache::FILENAME;
        if (is_file($file)) {
            unlink($file);
        }
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
        BatchBenchCache::clearStaticCache();
    }

    public function bench_200_puts_without_batch(): void
    {
        $cache = new BatchBenchCache($this->cacheDir);
        for ($i = 0; $i < 200; ++$i) {
            $cache->put('key' . $i, 'ClassName' . $i);
        }
    }

    public function bench_200_puts_inside_batch(): void
    {
        $cache = new BatchBenchCache($this->cacheDir);
        BatchBenchCache::beginBatch();
        for ($i = 0; $i < 200; ++$i) {
            $cache->put('key' . $i, 'ClassName' . $i);
        }
        BatchBenchCache::commitBatch();
    }
}

final class BatchBenchCache extends AbstractPhpFileCache
{
    public const FILENAME = 'gacela-batch-bench.php';

    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
