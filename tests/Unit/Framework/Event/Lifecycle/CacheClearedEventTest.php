<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Lifecycle;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Cache\CacheClearedEvent;
use GacelaTest\Fixtures\SpyEventDispatcher;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;

final class CacheClearedEventTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetInstance();
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    public function test_deleting_a_cache_file_dispatches_cache_cleared_event(): void
    {
        $spy = new SpyEventDispatcher();
        Config::createWithSetup((new SetupGacela())->setEventDispatcher($spy));

        $file = $this->createTempCacheFile();
        FileCache::delete($file);

        self::assertFileDoesNotExist($file);

        $clearedEvents = $spy->dispatchedEventsOf(CacheClearedEvent::class);
        self::assertCount(1, $clearedEvents);
        self::assertSame($file, $clearedEvents[0]->cacheFile());
    }

    public function test_deleting_a_missing_file_dispatches_nothing(): void
    {
        $spy = new SpyEventDispatcher();
        Config::createWithSetup((new SetupGacela())->setEventDispatcher($spy));

        FileCache::delete(sys_get_temp_dir() . '/gacela-does-not-exist.php');

        self::assertSame([], $spy->dispatchedEvents());
    }

    public function test_deleting_before_bootstrap_stays_silent(): void
    {
        Config::resetInstance();

        $file = $this->createTempCacheFile();
        FileCache::delete($file);

        self::assertFileDoesNotExist($file);
    }

    private function createTempCacheFile(): string
    {
        $file = sys_get_temp_dir() . '/gacela-cache-cleared-' . uniqid('', true) . '.php';
        file_put_contents($file, '<?php return [];');

        return $file;
    }
}
