<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\Cache\WritableDirectory;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

/**
 * The cache directory is shared, often on a volume that fills up or that
 * another process cleans. A class-name cache file that does not load used to
 * fail every bootstrap until someone deleted it by hand.
 */
final class CorruptCacheFileTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/gacela-corrupt-' . uniqid('', true);
        mkdir($this->cacheDir);
        AbstractPhpFileCache::resetCache();
        WritableDirectory::resetCache();
    }

    protected function tearDown(): void
    {
        AbstractPhpFileCache::resetCache();
        WritableDirectory::resetCache();
        DirectoryUtil::removeDir($this->cacheDir);
    }

    #[DataProvider('unloadableFiles')]
    public function test_a_cache_file_that_does_not_load_is_a_miss_and_is_rewritten(string $contents): void
    {
        $filename = TestPhpFileCache::absoluteFilename($this->cacheDir, TestPhpFileCache::FILENAME, '');
        file_put_contents($filename, $contents);

        $cache = new TestPhpFileCache($this->cacheDir);

        self::assertSame([], $cache->getAll());

        $cache->put('key', 'Value');

        self::assertSame(['key' => 'Value'], require $filename);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unloadableFiles(): iterable
    {
        yield 'truncated' => ["<?php return array(\n  'x' => "];
        yield 'empty' => [''];
        yield 'a scalar' => ['<?php return 42;'];
    }
}
