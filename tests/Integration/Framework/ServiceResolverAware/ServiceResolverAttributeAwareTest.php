<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolverAware;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

final class ServiceResolverAttributeAwareTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        DirectoryUtil::removeDir(__DIR__ . DIRECTORY_SEPARATOR . '.gacela');
    }

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    public function test_existing_service(): void
    {
        $dummy = new DummyAttributeServiceResolverAware();
        $actual = $dummy->getRepository()->findName();

        self::assertCount(1, InMemoryCache::getAllFromKey(CustomServicesPhpCache::class));
        self::assertSame('name', $actual);
    }

    #[Depends('test_existing_service')]
    public function test_existing_service_cached(): void
    {
        self::assertCount(0, InMemoryCache::getAllFromKey(CustomServicesPhpCache::class));

        $dummy = new DummyAttributeServiceResolverAware();
        $dummy->getRepository()->findName();
        $dummy->getRepository()->findName();

        self::assertCount(1, InMemoryCache::getAllFromKey(CustomServicesPhpCache::class));
    }
}
