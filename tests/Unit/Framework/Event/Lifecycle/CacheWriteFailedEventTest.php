<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Lifecycle;

use Gacela\Console\Application\Doctor\Check\CacheWritabilityCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Cache\CacheWriteFailedEvent;
use GacelaTest\Feature\Util\DirectoryUtil;
use GacelaTest\Fixtures\SpyEventDispatcher;
use GacelaTest\Unit\Framework\ClassResolver\Cache\TestPhpFileCache;
use PHPUnit\Framework\TestCase;

final class CacheWriteFailedEventTest extends TestCase
{
    public function test_a_failed_cache_write_dispatches_an_event_and_is_reported_by_doctor(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gacela-write-failure-' . uniqid('', true);
        mkdir($directory);
        $file = TestPhpFileCache::absoluteFilename($directory, TestPhpFileCache::FILENAME, '');
        mkdir($file);
        $spy = new SpyEventDispatcher();
        Config::createWithSetup((new SetupGacela())->setEventDispatcher($spy));
        TestPhpFileCache::resetCache();
        FileCache::resetWriteFailures();
        try {
            (new TestPhpFileCache($directory))->put('key', 'Value');
            $events = $spy->dispatchedEventsOf(CacheWriteFailedEvent::class);
            self::assertCount(1, $events);
            self::assertSame($file, $events[0]->cacheFile());
            self::assertStringContainsString($file, $events[0]->toString());
            $result = (new CacheWritabilityCheck(true, $directory))->run();
            self::assertSame(CheckStatus::Warn, $result->status);
            self::assertStringContainsString($file, implode(' ', $result->details));
        } finally {
            Config::resetInstance();
            TestPhpFileCache::resetCache();
            FileCache::resetWriteFailures();
            DirectoryUtil::removeDir($directory);
        }
    }
}
