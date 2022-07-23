<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\ClassResolver\Cache\GacelaCache;
use Gacela\Framework\Config\ConfigInterface;
use PHPUnit\Framework\TestCase;

final class GacelaCacheTest extends TestCase
{
    public function test_gacela_setup_is_disabled(): void
    {
        $setupGacela = $this->createMock(SetupGacelaInterface::class);
        $setupGacela->method('isCacheEnabled')
            ->willReturn(false);
        $config = $this->createMock(ConfigInterface::class);
        $config->method('getSetupGacela')
            ->willReturn($setupGacela);

        $gacelaCache = new GacelaCache($config);

        self::assertFalse($gacelaCache->isProjectCacheEnabled());
    }

    public function test_application_config_key_is_disabled(): void
    {
        $setupGacela = $this->createMock(SetupGacelaInterface::class);
        $setupGacela->method('isCacheEnabled')
            ->willReturn(true);
        $config = $this->createMock(ConfigInterface::class);
        $config->method('getSetupGacela')
            ->willReturn($setupGacela);
        $config->method('get')
            ->willReturn(false);

        $gacelaCache = new GacelaCache($config);

        self::assertFalse($gacelaCache->isProjectCacheEnabled());
    }

    public function test_cache_is_enabled(): void
    {
        $setupGacela = $this->createMock(SetupGacelaInterface::class);
        $setupGacela->method('isCacheEnabled')
            ->willReturn(true);
        $config = $this->createMock(ConfigInterface::class);
        $config->method('getSetupGacela')
            ->willReturn($setupGacela);
        $config->method('get')
            ->willReturn(true);

        $gacelaCache = new GacelaCache($config);

        self::assertTrue($gacelaCache->isProjectCacheEnabled());
    }
}
