<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomProfilerDirectory;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\ClassNameProfiler;
use Gacela\Framework\ClassResolver\DocBlockService\CustomServicesProfiler;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class FileProfilerFeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setProfilerEnabled(true);
            $config->setProfilerDirectory('custom/profiler-dir');
        });
    }

    public function tearDown(): void
    {
        DirectoryUtil::removeDir(__DIR__ . '/custom/profiler-dir');
    }

    public function test_custom_profiler_dir(): void
    {
        $facade = new Module\Facade();
        self::assertSame('name', $facade->getName());

        self::assertFileExists(__DIR__ . '/custom/profiler-dir/' . ClassNameProfiler::CACHE_FILENAME);
        self::assertFileExists(__DIR__ . '/custom/profiler-dir/' . CustomServicesProfiler::CACHE_FILENAME);
    }
}
