<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolverAware;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\DocBlockService\CustomServicesProfilerCache;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class DocBlockResolverCustomServicesAwareTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        CustomServicesProfilerCache::resetCache();
    }

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfig('config/custom-services/*.php');
        });
    }

    public function test_existing_service(): void
    {
        $dummy = new DummyDocBlockResolverAware();
        $actual = $dummy->getRepository()->findName();

        self::assertCount(1, CustomServicesProfilerCache::getAll());
        self::assertSame('name', $actual);
    }

    /**
     * @depends test_existing_service
     */
    public function test_existing_service_cached(): void
    {
        self::assertCount(1, CustomServicesProfilerCache::getAll());

        $dummy = new DummyDocBlockResolverAware();
        $dummy->getRepository()->findName();
        $dummy->getRepository()->findName();

        self::assertCount(1, CustomServicesProfilerCache::getAll());
    }

    public function test_non_existing_service(): void
    {
        $this->expectExceptionMessage('Missing the concrete return type for the method `getRepository()`');

        $dummy = new BadDummyDocBlockResolverAware();
        $dummy->getRepository();
    }
}
