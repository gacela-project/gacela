<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm;

use Gacela\Console\Application\CacheWarm\CacheWarmService;
use Gacela\Console\Application\CacheWarm\ClassNotFoundException;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Console\AllAppModules\Domain\Module1\Module1Facade;
use GacelaTest\Integration\Console\CacheWarm\AttributeWarm\AttributeWarmRealMethodService;
use GacelaTest\Integration\Console\CacheWarm\AttributeWarm\AttributeWarmRepository;
use GacelaTest\Integration\Console\CacheWarm\AttributeWarm\AttributeWarmService;
use GacelaTest\Integration\Console\CacheWarm\AttributeWarm\AttributeWarmServiceInterface;
use GacelaTest\Integration\Console\CacheWarm\AttributeWarm\AttributeWarmTypedFacade;
use PHPUnit\Framework\TestCase;

use function array_values;
use function class_exists;
use function implode;
use function rmdir;
use function uniqid;
use function unlink;

final class CacheWarmServiceTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '.gacela-cache-' . uniqid('', true);

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->resetInMemoryCache();
            $config->setFileCache(true, $cacheDir);
            $config->setProjectNamespaces(['GacelaTest\\Integration\\Console\\AllAppModules\\Domain']);
        });

        ClassNamePhpCache::clearStaticCache();
    }

    protected function tearDown(): void
    {
        ClassNamePhpCache::clearStaticCache();
        $this->removeCacheDir();
    }

    public function test_warm_class_resolution_populates_factory_config_and_provider_entries(): void
    {
        $service = new CacheWarmService();

        $service->warmClassResolution(Module1Facade::class);

        $resolved = implode('|', array_values(ClassNamePhpCache::all()));

        self::assertStringContainsString('Module1Factory', $resolved);
        self::assertStringContainsString('Module1Config', $resolved);
        self::assertStringContainsString('Module1Provider', $resolved);
    }

    public function test_warm_class_resolution_is_idempotent(): void
    {
        $service = new CacheWarmService();

        $service->warmClassResolution(Module1Facade::class);

        $firstRun = ClassNamePhpCache::all();

        $service->warmClassResolution(Module1Facade::class);
        $secondRun = ClassNamePhpCache::all();

        self::assertSame($firstRun, $secondRun);
    }

    public function test_warm_class_resolution_skips_when_facade_class_missing(): void
    {
        $resolverEvents = [];
        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir, &$resolverEvents): void {
            $config->resetInMemoryCache();
            $config->setFileCache(true, $cacheDir);
            $config->registerGenericListener(static function (GacelaEventInterface $event) use (&$resolverEvents): void {
                if (str_starts_with($event::class, 'Gacela\\Framework\\Event\\ClassResolver\\')) {
                    $resolverEvents[] = $event::class;
                }
            });
        });

        $service = new CacheWarmService();

        /** @var class-string $fake */
        $fake = 'Non\\Existing\\MissingFacade';
        $service->warmClassResolution($fake);

        // A missing class never reaches the on-disk cache either way, so only the
        // event stream shows the resolvers were never entered at all.
        self::assertSame([], $resolverEvents);
        self::assertSame([], ClassNamePhpCache::all());
    }

    public function test_filter_production_modules_only_drops_test_fixture_and_benchmark_namespace_segments(): void
    {
        $service = new CacheWarmService();

        // The dropped modules come first on purpose: the result is declared as a
        // list, so the surviving modules have to be reindexed from 0, not keep
        // the holes left behind by the filter.
        $modules = array_map(
            static fn (string $facade): AppModule => new AppModule($facade, 'name', $facade),
            [
                'App\\Test\\SomeFacade',
                'App\\Tests\\SomeFacade',
                'App\\Fixtures\\SomeFacade',
                'App\\Benchmark\\SomeFacade',
                'App\\Testimonial\\TestimonialFacade',
                'App\\Testament\\TestamentFacade',
            ],
        );

        $kept = array_map(
            static fn (AppModule $module): string => $module->facadeClass(),
            $service->filterProductionModules($modules),
        );

        // 'Test' as a substring of a real word (Testimonial, Testament) must survive;
        // only the \Test\, \Tests\, \Fixtures\, \Benchmark\ namespace segments are dropped.
        self::assertSame([
            0 => 'App\\Testimonial\\TestimonialFacade',
            1 => 'App\\Testament\\TestamentFacade',
        ], $kept);
    }

    public function test_module_classes_list_every_pillar_the_module_declares(): void
    {
        $service = new CacheWarmService();

        $module = new AppModule(
            'App\\Foo',
            'Foo',
            'App\\Foo\\FooFacade',
            'App\\Foo\\FooFactory',
            'App\\Foo\\FooConfig',
            'App\\Foo\\FooProvider',
        );

        self::assertSame([
            ['type' => 'Facade', 'className' => 'App\\Foo\\FooFacade'],
            ['type' => 'Factory', 'className' => 'App\\Foo\\FooFactory'],
            ['type' => 'Config', 'className' => 'App\\Foo\\FooConfig'],
            ['type' => 'Provider', 'className' => 'App\\Foo\\FooProvider'],
        ], $service->getModuleClasses($module));
    }

    public function test_module_classes_skip_the_pillars_the_module_does_not_declare(): void
    {
        $service = new CacheWarmService();

        $module = new AppModule('App\\Foo', 'Foo', 'App\\Foo\\FooFacade');

        self::assertSame(
            [['type' => 'Facade', 'className' => 'App\\Foo\\FooFacade']],
            $service->getModuleClasses($module),
        );
    }

    public function test_resolve_class_autoloads_a_class_that_exists(): void
    {
        $service = new CacheWarmService();

        $service->resolveClass(Module1Facade::class);

        self::assertTrue(class_exists(Module1Facade::class, false), 'resolveClass must autoload the class');
    }

    public function test_resolve_class_rejects_a_class_that_does_not_exist(): void
    {
        $service = new CacheWarmService();

        $this->expectException(ClassNotFoundException::class);
        $this->expectExceptionMessage('Class not found: Non\\Existing\\MissingClass');

        $service->resolveClass('Non\\Existing\\MissingClass');
    }

    public function test_warm_attribute_cache_resolves_the_service_map_methods(): void
    {
        CustomServicesPhpCache::clearStaticCache();

        (new CacheWarmService())->warmAttributeCache(AttributeWarmService::class);

        self::assertSame(
            [AttributeWarmService::class . '::getRepository' => AttributeWarmRepository::class],
            CustomServicesPhpCache::all(),
        );
    }

    /**
     * `__call()` is invoked only for a method the class does not have, so an
     * accessor that is also a real method never reaches the resolver -- and an
     * entry cached under its name is one nothing can look up.
     */
    public function test_warm_attribute_cache_skips_an_accessor_the_class_really_declares(): void
    {
        CustomServicesPhpCache::clearStaticCache();

        (new CacheWarmService())->warmAttributeCache(AttributeWarmRealMethodService::class);

        // The fixture declares that one first and a genuine accessor after it,
        // so this also pins that skipping does not end the walk.
        self::assertSame(
            [AttributeWarmRealMethodService::class . '::getOtherRepository' => AttributeWarmRepository::class],
            CustomServicesPhpCache::all(),
        );
    }

    /**
     * The symptom the change above is really about. Warming walked every public
     * method, so on any facade it resolved the inherited real `getFactory()`
     * through the docblock fallback -- reading the `@extends` generic and
     * raising the 3.0 deprecation for it. That accessor resolves through
     * `FactoryResolver` and the naming convention, so the notice named a call
     * that does not take that path and suggested an attribute that would not
     * change it: one per facade, on the command `UPGRADE.md` recommends for
     * surfacing the genuine ones.
     */
    public function test_warming_a_generically_typed_facade_raises_no_deprecation(): void
    {
        CustomServicesPhpCache::clearStaticCache();

        $notices = [];
        set_error_handler(
            static function (int $severity, string $message) use (&$notices): bool {
                $notices[] = $message;

                return true;
            },
            E_USER_DEPRECATED,
        );

        try {
            (new CacheWarmService())->warmAttributeCache(AttributeWarmTypedFacade::class);
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $notices);
        self::assertSame([], CustomServicesPhpCache::all());
    }

    public function test_warm_attribute_cache_skips_a_class_that_does_not_exist(): void
    {
        CustomServicesPhpCache::clearStaticCache();

        /** @var class-string $fake */
        $fake = 'Non\\Existing\\MissingService';
        (new CacheWarmService())->warmAttributeCache($fake);

        self::assertSame([], CustomServicesPhpCache::all());
    }

    public function test_warm_attribute_cache_skips_an_interface(): void
    {
        CustomServicesPhpCache::clearStaticCache();

        // ReflectionClass happily reflects an interface, so dropping the
        // class_exists() guard would cache a service for a method no instance has.
        (new CacheWarmService())->warmAttributeCache(AttributeWarmServiceInterface::class);

        self::assertSame([], CustomServicesPhpCache::all());
    }

    private function removeCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        foreach (glob($this->cacheDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->cacheDir);
    }
}
