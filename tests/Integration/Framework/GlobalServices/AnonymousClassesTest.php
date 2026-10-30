<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\GlobalServices;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\ClassResolver\MissingPillarMethodException;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassTriedFromParentEvent;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\GlobalServices\Counter\CounterFacade;
use GacelaTest\Integration\Framework\GlobalServices\Counter\CounterFactory;
use GacelaTest\Integration\Framework\GlobalServices\Doubles\CounterFacadeDouble;
use GacelaTest\Integration\Framework\GlobalServices\Doubles\EmptyFacadeDouble;
use GacelaTest\Integration\Framework\GlobalServices\Doubles\GreeterFacadeDouble;
use GacelaTest\Integration\Framework\GlobalServices\Doubles\NestedGreeterFacadeDouble;
use GacelaTest\Integration\Framework\GlobalServices\Empty\EmptyFacade;
use GacelaTest\Integration\Framework\GlobalServices\Greeter\GreeterFacade;
use GacelaTest\Integration\Framework\GlobalServices\Greeter\GreeterFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class AnonymousClassesTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });
    }

    public function test_an_anonymous_subclass_reaches_the_real_module_factory(): void
    {
        $facade = new class() extends GreeterFacade {};

        self::assertInstanceOf(GreeterFactory::class, $facade->getFactory());
        self::assertSame('total', $facade->total());
        self::assertSame((new GreeterFacade())->getFactory(), $facade->getFactory());
    }

    #[DataProvider('facadeResolutionOrders')]
    public function test_anonymous_subclasses_of_two_modules_keep_distinct_factories(bool $secondFirst): void
    {
        $greeter = new class() extends GreeterFacade {};
        $counter = new class() extends CounterFacade {};
        foreach ($secondFirst ? [$counter, $greeter] : [$greeter, $counter] as $facade) {
            $facade->getFactory();
        }

        self::assertInstanceOf(GreeterFactory::class, $greeter->getFactory());
        self::assertInstanceOf(CounterFactory::class, $counter->getFactory());
        self::assertSame('total', $greeter->total());
        self::assertSame('counter', $counter->total());
        self::assertNotSame($greeter->getFactory(), $counter->getFactory());
        self::assertSame($greeter->getFactory(), (new FactoryResolver())->resolve('\\' . $greeter::class));
        self::assertSame($counter->getFactory(), (new DocBlockServiceResolver('Factory'))->resolve($counter::class));
        self::assertSame(ClassInfo::from($greeter, 'Factory')->getCacheKey(), ClassInfo::from($counter, 'Factory')->getCacheKey());
    }

    #[DataProvider('facadeResolutionOrders')]
    public function test_anonymous_subclasses_keep_a_missing_factory_separate(bool $missingFirst): void
    {
        $greeter = new class() extends GreeterFacade {};
        $empty = new class() extends EmptyFacade {};
        foreach ($missingFirst ? [$empty, $greeter] : [$greeter, $empty] as $facade) {
            $facade->getFactory();
        }

        self::assertInstanceOf(GreeterFactory::class, $greeter->getFactory());
        $this->expectException(MissingPillarMethodException::class);
        $empty->getFactory()->createTotal();
    }

    public function test_file_scoped_globals_still_override_both_anonymous_callers(): void
    {
        $greeter = new class() extends GreeterFacade {};
        $counter = new class() extends CounterFacade {};
        $resolver = new FactoryResolver();
        $resolver->resolve($greeter);
        $resolver->resolve($counter);

        $global = new class() extends AbstractFactory {};
        Gacela::addGlobal($global, self::class);

        self::assertSame($global, $resolver->resolve($greeter));
        self::assertSame($global, $resolver->resolve($counter));
        self::assertSame($global, $greeter->getFactory());
        self::assertSame($global, $counter->getFactory());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function facadeResolutionOrders(): iterable
    {
        yield 'first module first' => [false];
        yield 'second module first' => [true];
    }

    #[DataProvider('facadeResolutionOrders')]
    public function test_named_doubles_in_one_namespace_keep_each_parent_factory(bool $secondFirst): void
    {
        $greeter = new GreeterFacadeDouble();
        $counter = new CounterFacadeDouble();
        foreach ($secondFirst ? [$counter, $greeter] : [$greeter, $counter] as $facade) {
            $facade->getFactory();
        }

        self::assertInstanceOf(GreeterFactory::class, $greeter->getFactory());
        self::assertInstanceOf(CounterFactory::class, $counter->getFactory());
        self::assertSame('total', $greeter->total());
        self::assertSame('counter', $counter->total());
        self::assertSame((new GreeterFacade())->getFactory(), $greeter->getFactory());
        self::assertSame((new CounterFacade())->getFactory(), $counter->getFactory());
    }

    #[DataProvider('facadeResolutionOrders')]
    public function test_named_doubles_keep_a_missing_parent_factory_separate(bool $missingFirst): void
    {
        $greeter = new GreeterFacadeDouble();
        $empty = new EmptyFacadeDouble();
        foreach ($missingFirst ? [$empty, $greeter] : [$greeter, $empty] as $facade) {
            $facade->getFactory();
        }

        self::assertInstanceOf(GreeterFactory::class, $greeter->getFactory());
        $this->expectException(MissingPillarMethodException::class);
        $empty->getFactory()->createTotal();
    }

    public function test_a_parent_factory_replacement_reaches_a_previously_warmed_double(): void
    {
        $original = (new GreeterFacadeDouble())->getFactory();
        $replacement = new class() extends AbstractFactory {};
        Gacela::overrideExistingResolvedClass(GreeterFactory::class, $replacement);
        AbstractFacade::resetCache();
        AbstractFactory::resetCache();

        self::assertNotSame($original, $replacement);
        self::assertSame($replacement, (new GreeterFacadeDouble())->getFactory());
    }

    /**
     * @param class-string<AbstractGacelaClassResolverEvent> $eventClass
     */
    #[DataProvider('resolutionMutationEvents')]
    public function test_a_resolution_listener_cannot_restore_an_invalidated_caller_instance(string $eventClass): void
    {
        $replacement = new class() extends AbstractFactory {};
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($eventClass, $replacement): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->registerSpecificListener($eventClass, static function (AbstractGacelaClassResolverEvent $event) use ($replacement): void {
                if ($event->classInfo()->getModuleName() === 'Greeter' && $event->classInfo()->getResolvableType() === 'Factory') {
                    Gacela::overrideExistingResolvedClass(GreeterFactory::class, $replacement);
                }
            });
        });
        if ($eventClass === ResolvedClassCachedEvent::class) {
            Gacela::overrideExistingResolvedClass(GreeterFactory::class, new class() extends AbstractFactory {});
        }

        $resolver = new FactoryResolver();
        $first = $resolver->resolve(GreeterFacadeDouble::class);

        self::assertNotSame($replacement, $first);
        self::assertSame($replacement, $resolver->resolve(GreeterFacade::class));
        self::assertSame($replacement, $resolver->resolve(GreeterFacadeDouble::class));
        self::assertSame($replacement, $resolver->resolve(GreeterFacadeDouble::class));
    }

    /**
     * @return iterable<string, array{class-string<AbstractGacelaClassResolverEvent>}>
     */
    public static function resolutionMutationEvents(): iterable
    {
        yield 'created parent' => [ResolvedClassCreatedEvent::class];
        yield 'cached parent' => [ResolvedClassCachedEvent::class];
    }

    public function test_resetting_globals_discards_the_inherited_replacement(): void
    {
        $replacement = new class() extends AbstractFactory {};
        Gacela::overrideExistingResolvedClass(GreeterFactory::class, $replacement);
        $resolver = new FactoryResolver();
        self::assertSame($replacement, $resolver->resolve(GreeterFacadeDouble::class));

        AnonymousGlobal::resetCache();

        self::assertInstanceOf(GreeterFactory::class, $resolver->resolve(GreeterFacadeDouble::class));
    }

    public function test_request_reset_discards_the_caller_default_instance(): void
    {
        $resolver = new FactoryResolver();
        $original = $resolver->resolve(EmptyFacadeDouble::class);
        self::assertSame($original, $resolver->resolve('\\' . EmptyFacadeDouble::class));

        Gacela::resetRequestState();

        self::assertNotSame($original, $resolver->resolve(EmptyFacadeDouble::class));
    }

    public function test_a_canonical_global_still_overrides_both_named_callers(): void
    {
        $greeter = new GreeterFacadeDouble();
        $counter = new CounterFacadeDouble();
        $resolver = new FactoryResolver();
        $resolver->resolve($greeter);
        $resolver->resolve($counter);

        $global = new class() extends AbstractFactory {};
        Gacela::overrideExistingResolvedClass(__NAMESPACE__ . '\\Doubles\\DoublesFactory', $global);

        self::assertSame($global, $resolver->resolve($greeter));
        self::assertSame($global, $resolver->resolve($counter));
        self::assertSame($global, $greeter->getFactory());
        self::assertSame($global, $counter->getFactory());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_local_factory_takes_precedence_over_both_parent_factories(): void
    {
        $localFactory = __NAMESPACE__ . '\\Doubles\\DoublesFactory';
        self::assertFalse(class_exists($localFactory, false));
        self::assertTrue(class_alias(CounterFactory::class, $localFactory));
        $greeter = new GreeterFacadeDouble();
        $counter = new CounterFacadeDouble();

        self::assertInstanceOf(CounterFactory::class, $greeter->getFactory());
        self::assertSame($greeter->getFactory(), $counter->getFactory());
        self::assertSame($greeter->getFactory(), (new NestedGreeterFacadeDouble())->getFactory());
    }

    public function test_a_named_subclass_reaches_the_real_module_factory(): void
    {
        $original = (new GreeterFacade())->getFactory();
        $facade = new GreeterFacadeDouble();

        self::assertSame($original, $facade->getFactory());
        self::assertSame('total', $facade->total());
    }

    public function test_parent_resolution_skips_repeated_module_keys(): void
    {
        $facade = new NestedGreeterFacadeDouble();

        self::assertSame('total', $facade->total());
        self::assertSame((new GreeterFacade())->getFactory(), $facade->getFactory());
    }

    public function test_class_string_and_docblock_resolvers_reach_the_parent_factory(): void
    {
        $expected = (new GreeterFacade())->getFactory();

        self::assertSame($expected, (new FactoryResolver())->resolve(GreeterFacadeDouble::class));
        self::assertSame($expected, (new DocBlockServiceResolver('Factory'))->resolve(GreeterFacadeDouble::class));
    }

    public function test_the_facade_parent_retry_emits_one_event_per_new_module_key(): void
    {
        $events = [];
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$events): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->registerSpecificListener(ResolvedClassTriedFromParentEvent::class, static function (ResolvedClassTriedFromParentEvent $event) use (&$events): void {
                $events[] = $event;
            });
        });
        (new NestedGreeterFacadeDouble())->getFactory();

        self::assertEquals([new ResolvedClassTriedFromParentEvent(ClassInfo::from(NestedGreeterFacadeDouble::class, 'Factory'))], $events);
    }

    public function test_a_parent_factory_override_is_inherited_without_a_factory_file(): void
    {
        $factory = new class() extends AbstractFactory {};
        Gacela::overrideExistingResolvedClass(__NAMESPACE__ . '\\Empty\\EmptyFactory', $factory);
        $facade = new class() extends EmptyFacade {};

        self::assertSame($factory, $facade->getFactory());
    }

    public function test_a_missing_module_factory_keeps_its_diagnostic_after_the_base_placeholder_was_resolved(): void
    {
        self::assertInstanceOf(AbstractFactory::class, (new DocBlockServiceResolver('Factory'))->resolve(AbstractFactory::class));
        $this->expectException(MissingPillarMethodException::class);
        $this->expectExceptionMessage('Module `Empty` has no `Factory`');

        (new EmptyFacade())->getFactory()->createTotal();
    }

    public function test_an_unknown_class_string_still_resolves_the_default_factory(): void
    {
        $caller = 'GacelaTest\\UnknownModule\\MissingFacade';
        self::assertFalse(class_exists($caller));
        $factory = (new FactoryResolver())->resolve($caller);

        self::assertInstanceOf(AbstractFactory::class, $factory);
        $this->expectException(MissingPillarMethodException::class);
        $this->expectExceptionMessage('Module `UnknownModule` has no `Factory`');
        $factory->createTotal();
    }

    public function test_class_resolver_using_anonymous_classes(): void
    {
        $this->registerConfig();
        $this->registerFactory();
        $this->registerAbstractProvider();

        $facade = new class() extends AbstractFacade {
            public function getSomething(): array
            {
                return $this->getFactory()
                    ->createDomainClass()
                    ->getConfigValues();
            }

            public function greet(string $name): string
            {
                return $this->getFactory()
                    ->createDomainClass()
                    ->greet($name);
            }
        };

        self::assertSame([1, 2, 3, 4, 5], $facade->getSomething());
        self::assertSame('Hello, Chema!', $facade->greet('Chema'));
    }

    private function registerConfig(): void
    {
        Gacela::addGlobal(
            new class() extends AbstractConfig {
                /**
                 * @return int[]
                 */
                public function getValues(): array
                {
                    return [1, 2, 3, 4, 5];
                }
            },
            $this,
        );
    }

    private function registerFactory(): void
    {
        Gacela::addGlobal(
            new class() extends AbstractFactory {
                public function createDomainClass(): object
                {
                    /** @var object $myService */
                    $myService = $this->getProvidedDependency('my-greeter');

                    /** @var int[] $configValues */
                    $configValues = $this->getConfig()->getValues();

                    return new class($myService, ...$configValues) {
                        /** @var int[] */
                        private readonly array $configValues;

                        public function __construct(
                            private readonly object $myService,
                            int ...$configValues,
                        ) {
                            $this->configValues = $configValues;
                        }

                        public function getConfigValues(): array
                        {
                            return $this->configValues;
                        }

                        public function greet(string $name): string
                        {
                            return $this->myService->greet($name);
                        }
                    };
                }
            },
            self::class,
        );
    }

    private function registerAbstractProvider(): void
    {
        Gacela::addGlobal(
            new class() extends AbstractProvider {
                public function provideModuleDependencies(Container $container): void
                {
                    $container->set('my-greeter', new class() {
                        public function greet(string $name): string
                        {
                            return sprintf('Hello, %s!', $name);
                        }
                    });
                }
            },
        );
    }
}
