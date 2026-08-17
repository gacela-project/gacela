<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\Testing\GacelaTestCase;
use Gacela\Framework\Testing\ModuleDoubleException;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Ordering\OrderingFacade;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Ordering\OrderingFactory;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Ordering\OrderingProvider;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing\Domain\PriceList;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing\PricingConfig;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing\PricingFacade;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Pricing\PricingProvider;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Tax\TaxRateInterface;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Tax\ZeroTaxRate;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shipping\ShippingFacade;
use ReflectionClass;

use function array_map;
use function dirname;

/**
 * Testing one module with its neighbours replaced is the everyday test of a
 * modular application. Every primitive for it already existed; what did not was
 * one call that picks the right one per double and narrows the application to
 * the module under test.
 */
final class ModuleSliceTest extends GacelaTestCase
{
    private const FIXTURE_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'ModuleSliceFixture';

    public function test_the_module_answers_with_its_real_neighbours_when_nothing_is_doubled(): void
    {
        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class);

        self::assertSame('price:1000 shipping:500 tax:2100', (new OrderingFacade())->quote('widget'));
    }

    /**
     * The slice: discovery sees the module under test and nothing else, so
     * `doctor`, `list:modules` and the boundary assertions answer about one
     * module instead of about the whole application.
     */
    public function test_the_slice_narrows_module_discovery_to_the_module_under_test(): void
    {
        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class);

        self::assertSame(
            [dirname((string)(new ReflectionClass(OrderingFacade::class))->getFileName())],
            Config::getInstance()->getSetupGacela()->getAppModulePaths(),
        );
    }

    /**
     * A neighbour the module reaches through its Provider, which resolves it
     * from the locator.
     */
    public function test_a_facade_double_answers_where_the_provider_reaches_for_the_module(): void
    {
        $pricing = $this->createStub(PricingFacade::class);
        $pricing->method('priceOf')->willReturn(7);

        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            PricingFacade::class => $pricing,
        ]);

        self::assertSame('price:7 shipping:500 tax:2100', (new OrderingFacade())->quote('widget'));
    }

    /**
     * The other way a module reaches a neighbour. Both go through the locator,
     * so one primitive covers them -- which is exactly what a caller should not
     * have to know.
     */
    public function test_a_facade_double_answers_where_a_service_map_accessor_reaches_for_the_module(): void
    {
        $shipping = $this->createStub(ShippingFacade::class);
        $shipping->method('costOf')->willReturn(42);

        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            ShippingFacade::class => $shipping,
        ]);

        self::assertSame('price:1000 shipping:42 tax:2100', (new OrderingFacade())->quote('widget'));
    }

    /**
     * An interface the composition root answers rather than a module: the
     * container is the seam, and the double has to become a binding.
     */
    public function test_an_interface_double_becomes_a_container_binding(): void
    {
        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            TaxRateInterface::class => new class() implements TaxRateInterface {
                public function basisPoints(): int
                {
                    return 0;
                }
            },
        ]);

        self::assertSame('price:1000 shipping:500 tax:0', (new OrderingFacade())->quote('widget'));
        $this->assertBindingRegistered(TaxRateInterface::class);
    }

    /**
     * A container id is a string nobody can reflect on, and it is how a Provider
     * names what it provides -- which is also the one thing an application-level
     * binding cannot reach, because the Provider registers after it.
     */
    public function test_a_container_id_double_replaces_what_the_provider_registers(): void
    {
        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            OrderingProvider::TAX_RATE => new class() implements TaxRateInterface {
                public function basisPoints(): int
                {
                    return 1;
                }
            },
        ]);

        self::assertSame('price:1000 shipping:500 tax:1', (new OrderingFacade())->quote('widget'));
    }

    /**
     * A class-string value is a binding rather than an instance override: the
     * container is what turns it into an object.
     */
    public function test_a_class_string_double_becomes_a_container_binding(): void
    {
        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            TaxRateInterface::class => ZeroTaxRate::class,
        ]);

        self::assertSame('price:1000 shipping:500 tax:0', (new OrderingFacade())->quote('widget'));
    }

    public function test_a_callable_double_becomes_a_container_binding(): void
    {
        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            TaxRateInterface::class => static fn (): TaxRateInterface => new ZeroTaxRate(),
        ]);

        self::assertSame('price:1000 shipping:500 tax:0', (new OrderingFacade())->quote('widget'));
    }

    /**
     * A Factory double is keyed by the Facade whose module it replaces, and the
     * value's own type is what says which pillar is meant. `PricingFactory` is
     * final, so the double is a standalone `AbstractFactory`.
     */
    public function test_a_factory_double_replaces_the_neighbours_factory(): void
    {
        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            PricingFacade::class => new class() extends AbstractFactory {
                public function createPriceList(): PriceList
                {
                    return new PriceList(['widget' => 3]);
                }
            },
        ]);

        self::assertSame('price:3 shipping:500 tax:2100', (new OrderingFacade())->quote('widget'));
    }

    public function test_a_config_double_replaces_the_neighbours_config(): void
    {
        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            PricingFacade::class => new class() extends PricingConfig {
                public function currency(): string
                {
                    return 'CHF';
                }
            },
        ]);

        self::assertSame('CHF', (new PricingFacade())->currency());
    }

    public function test_a_provider_double_replaces_the_neighbours_provider(): void
    {
        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            PricingFacade::class => new class() extends PricingProvider {
                public function provideModuleDependencies(Container $container): void
                {
                    $container->set(self::CATALOGUE_NAME, 'the doubled catalogue');
                }
            },
        ]);

        self::assertSame('the doubled catalogue', (new PricingFacade())->catalogueName());
    }

    /**
     * The acceptance criterion, at the framework level: a neighbour that is
     * doubled is never built. The events say so; trusting the setup would not.
     */
    public function test_a_doubled_neighbour_never_has_its_pillars_built(): void
    {
        $pricing = $this->createStub(PricingFacade::class);
        $pricing->method('priceOf')->willReturn(7);

        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            PricingFacade::class => $pricing,
        ]);

        (new OrderingFacade())->quote('widget');

        self::assertNotContains('Pricing', $this->modulesWhoseClassesWereBuilt());
        self::assertContains('Ordering', $this->modulesWhoseClassesWereBuilt());
    }

    /**
     * Composed with the narrowing rather than replacing it, so a slice can still
     * configure the application it is a slice of.
     */
    public function test_the_bootstrap_closure_runs_and_keeps_the_narrowing(): void
    {
        $this->bootstrapModule(
            self::FIXTURE_DIR,
            OrderingFacade::class,
            configFn: static function (GacelaConfig $config): void {
                $config->addLazy(TaxRateInterface::class, static fn (): TaxRateInterface => new ZeroTaxRate());
            },
        );

        self::assertSame('price:1000 shipping:500 tax:0', (new OrderingFacade())->quote('widget'));
        self::assertCount(1, Config::getInstance()->getSetupGacela()->getAppModulePaths());
    }

    /**
     * The same guard `swapModuleFactory()` has, for the same reason: a pillar
     * double is registered under the key its module's own class would take, and
     * only a Facade names a module.
     */
    public function test_a_pillar_double_keyed_by_something_that_is_not_a_facade_fails_loudly(): void
    {
        $this->expectException(ModuleDoubleException::class);
        $this->expectExceptionMessage(OrderingFactory::class);

        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            OrderingFactory::class => new class() extends AbstractFactory {},
        ]);
    }

    /**
     * The bogus double this *can* detect: an object that is not an instance of
     * the class it is registered under would be handed to a consumer that
     * type-hints the real one, and the resulting TypeError names neither the
     * test nor the double.
     */
    public function test_a_double_that_is_not_an_instance_of_the_class_it_replaces_fails_loudly(): void
    {
        $this->expectException(ModuleDoubleException::class);
        $this->expectExceptionMessage(PricingFacade::class);

        $this->bootstrapModule(self::FIXTURE_DIR, OrderingFacade::class, doubles: [
            PricingFacade::class => new ZeroTaxRate(),
        ]);
    }

    /**
     * The Facade is the name a consumer knows, and naming anything else leaves
     * a double nothing would ever read.
     */
    public function test_slicing_a_module_that_is_not_named_by_a_facade_fails_loudly(): void
    {
        $this->expectException(ModuleDoubleException::class);
        $this->expectExceptionMessage('There is no class');

        /** @psalm-suppress ArgumentTypeCoercion,UndefinedClass a typo is the mistake under test */
        $this->bootstrapModule(self::FIXTURE_DIR, 'App\Nope\NopeFacade');
    }

    /**
     * The names of the modules whose classes the resolver built since the
     * bootstrap.
     *
     * @return list<string>
     */
    private function modulesWhoseClassesWereBuilt(): array
    {
        return array_map(
            static fn (ResolvedClassCreatedEvent $event): string => $event->classInfo()->getModuleName(),
            $this->recordedGacelaEventsOf(ResolvedClassCreatedEvent::class),
        );
    }
}
