<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins\Membership;

use Gacela\Framework\Plugins\Membership\Members;
use Gacela\Framework\Plugins\Membership\MembershipCache;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MembershipCacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-membership-' . bin2hex(random_bytes(8));
        mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        DirectoryUtil::removeDir($this->cacheDir);
    }

    #[DataProvider('unusableFiles')]
    public function test_an_unusable_cache_file_is_a_miss(string $contents): void
    {
        $cache = MembershipCache::forScan($this->cacheDir, $this->cacheDir, [], []);
        file_put_contents($cache->path(), $contents);

        self::assertTrue($cache->isWarm());
        self::assertNull($cache->read());
    }

    public function test_a_legacy_plugin_only_cache_is_read(): void
    {
        $cache = MembershipCache::forScan($this->cacheDir, $this->cacheDir, [], []);
        file_put_contents($cache->path(), "<?php return ['plugins' => []];");

        self::assertEquals(new Members(), $cache->read());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableFiles(): iterable
    {
        yield 'truncated' => ["<?php return ['plugins' => [["];
        yield 'non-array' => ['<?php return 42;'];
        yield 'missing plugins' => ['<?php return [];'];
        yield 'non-array plugins' => ["<?php return ['plugins' => 42];"];
    }
}
