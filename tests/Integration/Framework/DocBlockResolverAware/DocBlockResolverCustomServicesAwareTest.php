<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolverAware;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

final class DocBlockResolverCustomServicesAwareTest extends TestCase
{
    private const CACHE_DIR = __DIR__ . DIRECTORY_SEPARATOR . '.gacela';

    public static function setUpBeforeClass(): void
    {
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache(__DIR__);
            $config->addAppConfigKeyValue(GacelaFileCache::KEY_ENABLED, true);
            $config->registerGenericListener(static function (GacelaEventInterface $event): void {
                // dump('Triggered -> ' . \get_class($event)); # useful for debugging
            });
        });
    }

    public function test_existing_service(): void
    {
        $dummy = new DummyDocBlockResolverAware();
        $actual = $dummy->getRepository()->findName();

        self::assertCount(2, CustomServicesPhpCache::all());
        self::assertSame('name', $actual);
    }

    #[Depends('test_existing_service')]
    public function test_existing_service_cached(): void
    {
        self::assertCount(2, CustomServicesPhpCache::all());

        $dummy = new DummyDocBlockResolverAware();
        $dummy->getRepository()->findName();
        $dummy->getRepository()->findName();

        self::assertCount(2, CustomServicesPhpCache::all());
    }

    public function test_non_existing_service(): void
    {
        $this->expectExceptionMessage('Missing the concrete return type for the method `getRepository()`');

        $dummy = new BadDummyDocBlockResolverAware();
        $dummy->getRepository();
    }
}
