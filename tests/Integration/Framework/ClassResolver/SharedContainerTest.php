<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver;

use Gacela\Container\Container;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\Provider\ProviderResolver;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Supplier\Facade;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SharedContainerTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetInstance();
        AbstractClassResolver::resetCache();
        InMemoryCache::resetCache();
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
        AbstractClassResolver::resetCache();
        InMemoryCache::resetCache();
    }

    public function test_resolvers_share_a_single_container_instance(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });

        $caller = new Facade();

        (new FactoryResolver())->resolve($caller);
        $afterFactory = $this->sharedContainer();
        self::assertInstanceOf(Container::class, $afterFactory);

        (new ConfigResolver())->resolve($caller);
        (new ProviderResolver())->resolve($caller);

        self::assertSame(
            $afterFactory,
            $this->sharedContainer(),
            'resolving Factory, Config and Provider must reuse one Container',
        );
    }

    public function test_reset_cache_clears_the_shared_container(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });

        (new FactoryResolver())->resolve(new Facade());
        self::assertInstanceOf(Container::class, $this->sharedContainer());

        AbstractClassResolver::resetCache();

        self::assertNull($this->sharedContainer());
    }

    private function sharedContainer(): ?Container
    {
        $property = new ReflectionProperty(AbstractClassResolver::class, 'container');

        /** @var Container|null $value */
        $value = $property->getValue();

        return $value;
    }
}
