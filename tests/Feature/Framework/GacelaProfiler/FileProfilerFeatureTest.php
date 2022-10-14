<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\GacelaProfiler;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Profiler\ClassNameJsonProfiler;
use Gacela\Framework\ClassResolver\Profiler\CustomServicesJsonProfiler;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class FileProfilerFeatureTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        DirectoryUtil::removeDir(__DIR__ . '/custom/profiler-dir');
    }

    protected function setUp(): void
    {
        DirectoryUtil::removeDir(__DIR__ . '/custom/profiler-dir');
    }

    public function test_custom_profiler_dir(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setProfilerEnabled(true);
            $config->setProfilerDirectory('/custom/profiler-dir');
        });

        $facade = new Module\Facade();
        self::assertSame('name', $facade->getName());

        self::assertFileExists(__DIR__ . '/custom/profiler-dir/' . ClassNameJsonProfiler::FILENAME);
        self::assertFileExists(__DIR__ . '/custom/profiler-dir/' . CustomServicesJsonProfiler::FILENAME);
    }

    public function test_custom_profiler_dir_but_profiler_disable(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setProfilerEnabled(false);
            $config->setProfilerDirectory('/custom/profiler-dir');
        });

        $facade = new Module\Facade();
        self::assertSame('name', $facade->getName());

        self::assertFileDoesNotExist(__DIR__ . '/custom/profiler-dir/' . ClassNameJsonProfiler::FILENAME);
        self::assertFileDoesNotExist(__DIR__ . '/custom/profiler-dir/' . CustomServicesJsonProfiler::FILENAME);
    }
}
