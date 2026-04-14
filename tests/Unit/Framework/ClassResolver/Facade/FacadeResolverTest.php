<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Facade;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\Facade\FacadeResolver;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FacadeResolverTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetInstance();
        AbstractClassResolver::resetCache();
        AnonymousGlobal::resetCache();
        InMemoryCache::resetCache();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
        AbstractClassResolver::resetCache();
        AnonymousGlobal::resetCache();
        InMemoryCache::resetCache();
    }

    public function test_resolves_default_anonymous_facade_when_caller_has_no_concrete_facade(): void
    {
        $callerWithoutFacade = new class() {
        };

        $resolved = (new FacadeResolver())->resolve($callerWithoutFacade);

        self::assertInstanceOf(AbstractFacade::class, $resolved);
    }
}
