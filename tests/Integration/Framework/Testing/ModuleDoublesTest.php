<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Testing\GacelaTestCase;
use Gacela\Framework\Testing\ModuleDoubleException;
use GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting\Domain\Greeter;
use GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting\GreetingConfig;
use GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting\GreetingFacade;
use GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting\GreetingFactory;
use GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting\GreetingProvider;
use GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Sealed\Domain\SealedGreeter;
use GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Sealed\SealedFacade;

/**
 * Replacing another module is the most common thing a test of a modular
 * application does, and the framework had no supported way to do it.
 */
final class ModuleDoublesTest extends GacelaTestCase
{
    private const FIXTURE_DIR = __DIR__ . '/ModuleDoubleFixture';

    /**
     * A `final` Factory -- which is what `make:module` generates, and what the
     * rest of this codebase prefers -- cannot be subclassed, so neither the
     * anonymous subclass this file uses elsewhere nor `createStub()` can double
     * it: PHP raises a fatal error, PHPUnit a `ClassIsFinalException`.
     *
     * `swapModuleFactory()` takes an `AbstractFactory`, not the module's own
     * one, so the double is a standalone factory carrying the methods the Facade
     * calls. Every other fixture here is deliberately non-final, which is how a
     * shape the scaffolder always produces went untested.
     */
    public function test_a_final_factory_is_doubled_by_a_standalone_one(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        self::assertSame('hello', (new SealedFacade())->greet(), 'precondition: the real module answers');

        $this->swapModuleFactory(SealedFacade::class, new class() extends AbstractFactory {
            public function createGreeter(): SealedGreeter
            {
                return new SealedGreeter('swapped');
            }
        });

        self::assertSame('swapped', (new SealedFacade())->greet());
    }

    public function test_a_swapped_factory_is_what_the_facade_uses(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        $this->swapModuleFactory(GreetingFacade::class, $this->factoryDouble('swapped'));

        self::assertSame('swapped', (new GreetingFacade())->greet());
    }

    /**
     * The Facade keeps its Factory in a static, so a module already touched
     * would otherwise keep answering with the real one -- the failure mode that
     * made the underlying `AnonymousGlobal` call so easy to get wrong.
     */
    public function test_a_module_resolved_before_the_swap_still_picks_the_double_up(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        self::assertSame('hello', (new GreetingFacade())->greet(), 'precondition: the real module answers');

        $this->swapModuleFactory(GreetingFacade::class, $this->factoryDouble('swapped'));

        self::assertSame('swapped', (new GreetingFacade())->greet());
    }

    public function test_the_swap_holds_across_repeated_resolutions(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        $this->swapModuleFactory(GreetingFacade::class, $this->factoryDouble('swapped'));

        self::assertSame('swapped', (new GreetingFacade())->greet());
        self::assertSame('swapped', (new GreetingFacade())->greet());
        self::assertSame('swapped', (new GreetingFacade())->greet());
    }

    public function test_the_last_swap_of_a_module_wins(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        $this->swapModuleFactory(GreetingFacade::class, $this->factoryDouble('first'));
        $this->swapModuleFactory(GreetingFacade::class, $this->factoryDouble('second'));

        self::assertSame('second', (new GreetingFacade())->greet());
    }

    /**
     * Half of the isolation claim, and it has to hold in either execution
     * order: this suite runs with a random seed.
     *
     * @see test_a_swapped_factory_is_what_the_facade_uses for the test that swaps
     */
    public function test_another_test_in_this_process_still_sees_the_real_module(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        self::assertSame('hello', (new GreetingFacade())->greet());
    }

    public function test_a_phpunit_test_double_is_accepted(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        $double = $this->createStub(GreetingFactory::class);
        $double->method('createGreeter')->willReturn(new Greeter('mocked'));

        $this->swapModuleFactory(GreetingFacade::class, $double);

        self::assertSame('mocked', (new GreetingFacade())->greet());
    }

    public function test_a_swapped_config_is_what_the_factory_reads(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        $this->swapModuleConfig(GreetingFacade::class, new class() extends GreetingConfig {
            public function language(): string
            {
                return 'eo';
            }
        });

        self::assertSame('eo', (new GreetingFacade())->language());
    }

    public function test_a_swapped_provider_is_what_wires_the_module(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        $this->swapModuleProvider(GreetingFacade::class, new class() extends GreetingProvider {
            public function provideModuleDependencies(Container $container): void
            {
                $container->set(self::GREETING, 'from the swapped provider');
            }
        });

        self::assertSame('from the swapped provider', (new GreetingFacade())->providedGreeting());
    }

    public function test_swapping_a_class_that_is_not_a_facade_fails_loudly(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        $this->expectException(ModuleDoubleException::class);
        $this->expectExceptionMessage(GreetingFactory::class);

        /** @psalm-suppress InvalidArgument the wrong pillar is exactly the mistake under test */
        $this->swapModuleFactory(GreetingFactory::class, $this->factoryDouble('never used'));
    }

    public function test_swapping_a_class_that_does_not_exist_fails_loudly(): void
    {
        $this->bootstrapGacela(self::FIXTURE_DIR);

        $this->expectException(ModuleDoubleException::class);
        $this->expectExceptionMessage('There is no class');

        /** @psalm-suppress ArgumentTypeCoercion,UndefinedClass a typo is the mistake under test */
        $this->swapModuleFactory('App\Nope\NopeFacade', $this->factoryDouble('never used'));
    }

    private function factoryDouble(string $greeting): GreetingFactory
    {
        return new class($greeting) extends GreetingFactory {
            public function __construct(
                private readonly string $greeting,
            ) {
            }

            public function createGreeter(): Greeter
            {
                return new Greeter($this->greeting);
            }
        };
    }
}
