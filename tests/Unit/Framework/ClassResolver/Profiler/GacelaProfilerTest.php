<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Profiler;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\ClassResolver\Profiler\GacelaProfiler;
use Gacela\Framework\Config\ConfigInterface;
use PHPUnit\Framework\TestCase;

final class GacelaProfilerTest extends TestCase
{
    public function tearDown(): void
    {
        GacelaProfiler::resetCache();
    }

    public function test_profiler_has_config_key_and_enabled(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('hasKey')->willReturn(true);
        $config->method('get')->willReturn(true);

        $profiler = new GacelaProfiler($config);

        self::assertTrue($profiler->isEnabled());
    }

    public function test_profiler_has_config_key_and_disabled(): void
    {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('hasKey')->willReturn(true);
        $config->method('get')->willReturn(false);

        $profiler = new GacelaProfiler($config);

        self::assertFalse($profiler->isEnabled());
    }

    public function test_profiler_config_key_is_disabled_then_returns_true_flag_from_setup(): void
    {
        $setupGacela = $this->createMock(SetupGacelaInterface::class);
        $setupGacela->method('isProfilerEnabled')->willReturn(true);

        $config = $this->createMock(ConfigInterface::class);
        $config->method('hasKey')->willReturn(false);
        $config->method('getSetupGacela')->willReturn($setupGacela);

        $profiler = new GacelaProfiler($config);

        self::assertTrue($profiler->isEnabled());
    }

    public function test_profiler_config_key_is_disabled_then_returns_false_flag_from_setup(): void
    {
        $setupGacela = $this->createMock(SetupGacelaInterface::class);
        $setupGacela->method('isProfilerEnabled')->willReturn(false);

        $config = $this->createMock(ConfigInterface::class);
        $config->method('hasKey')->willReturn(false);
        $config->method('getSetupGacela')->willReturn($setupGacela);

        $profiler = new GacelaProfiler($config);

        self::assertFalse($profiler->isEnabled());
    }
}
