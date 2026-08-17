<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\PillarContainer;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting\Facade;
use GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting\FriendlyGreeter;
use GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting\GreeterInterface;
use GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting\GrumpyGreeter;
use GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting\PoliteGreeter;
use GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting\SecondGreeterInterface;
use PHPUnit\Framework\TestCase;

use function array_count_values;

/**
 * The container the four pillars are built from is configured exactly like the
 * application container, so every registration verb reaches a Facade, Factory,
 * Config or Provider constructor -- not only `addBinding()` and `when()`.
 *
 * Two containers used to give two answers for one id: `loadDefinitions()` bound
 * an interface the application container resolved happily, while a Factory
 * asking for the same interface in its constructor failed to build.
 */
final class PillarContainerConfigTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetGacela();
    }

    protected function tearDown(): void
    {
        $this->resetGacela();
    }

    public function test_a_definition_satisfies_a_pillar_constructor(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->loadDefinitions([GreeterInterface::class => ['singleton' => FriendlyGreeter::class]]);
        });

        self::assertSame('hello from a definition', (new Facade())->greet());
    }

    public function test_a_definitions_file_satisfies_a_pillar_constructor(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->loadDefinitions(__DIR__ . DIRECTORY_SEPARATOR . 'services.php');
        });

        self::assertSame('hello from a definitions file', (new Facade())->greet());
    }

    public function test_a_definition_overrides_a_binding_for_a_pillar_constructor(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->addBinding(GreeterInterface::class, FriendlyGreeter::class);
            $config->loadDefinitions([GreeterInterface::class => ['singleton' => GrumpyGreeter::class]]);
        });

        // Definitions apply last so the data layer wins -- the same ordering the
        // application container has always had.
        self::assertSame('hello from a definitions file', (new Facade())->greet());
    }

    public function test_the_pillar_container_and_the_app_container_answer_the_same_id_alike(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->loadDefinitions([GreeterInterface::class => ['singleton' => FriendlyGreeter::class]]);
        });

        $fromPillar = (new Facade())->getFactory()->greeter();
        $fromApp = Gacela::container()->get(GreeterInterface::class);

        self::assertInstanceOf(FriendlyGreeter::class, $fromPillar);
        self::assertInstanceOf(FriendlyGreeter::class, $fromApp);
    }

    public function test_an_after_resolving_hook_reaches_a_pillar(): void
    {
        $resolved = [];

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$resolved): void {
            $config->loadDefinitions([GreeterInterface::class => ['singleton' => FriendlyGreeter::class]]);
            $config->afterResolving(
                Greeting\Factory::class,
                static function (object $instance) use (&$resolved): void {
                    $resolved[] = $instance::class;
                },
            );
        });

        (new Facade())->greet();

        self::assertSame([Greeting\Factory::class], $resolved);
    }

    /**
     * The id-keyed verbs never fill a constructor parameter -- autowiring
     * matches by type, in every container -- but a Provider reading one off
     * this container used to get nothing at all.
     */
    public function test_the_id_keyed_registrations_reach_the_pillar_container(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->addFactory('greeter.factory', static fn (): GreeterInterface => new FriendlyGreeter());
            $config->addLazy('greeter.lazy', static fn (): GreeterInterface => new FriendlyGreeter());
            $config->addAlias('greeter.alias', FriendlyGreeter::class);
            $config->addFactory('greeter.extended', static fn (): GreeterInterface => new FriendlyGreeter());
            $config->extendService('greeter.extended', static fn (): GreeterInterface => new GrumpyGreeter());
        });

        $container = AbstractClassResolver::pillarContainer();

        self::assertInstanceOf(FriendlyGreeter::class, $container->get('greeter.factory'));
        self::assertInstanceOf(FriendlyGreeter::class, $container->get('greeter.lazy'));
        self::assertInstanceOf(FriendlyGreeter::class, $container->get('greeter.alias'));
        self::assertInstanceOf(GrumpyGreeter::class, $container->get('greeter.extended'));
    }

    /**
     * `BindingRegisteredEvent` belongs to the configuration, which is walked
     * once however many containers apply it -- so the application container
     * announces the walk and the pillar container stays silent. Asserted at both
     * moments, because "once in total" alone is also true of announcing from the
     * wrong container.
     */
    public function test_the_configuration_is_announced_once_by_the_application_container(): void
    {
        $registered = [];

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$registered): void {
            $config->addBinding(SecondGreeterInterface::class, PoliteGreeter::class);
            $config->loadDefinitions([GreeterInterface::class => ['singleton' => FriendlyGreeter::class]]);
            $config->registerSpecificListener(
                BindingRegisteredEvent::class,
                static function (BindingRegisteredEvent $event) use (&$registered): void {
                    $registered[] = $event->id();
                },
            );
        });

        // Bootstrap builds the application container, which is where the walk
        // is announced -- before any pillar has been resolved.
        $atBootstrap = array_count_values($registered);
        self::assertSame(1, $atBootstrap[SecondGreeterInterface::class] ?? 0, 'the binding was not announced at bootstrap');
        self::assertSame(1, $atBootstrap[GreeterInterface::class] ?? 0, 'the definition was not announced at bootstrap');

        (new Facade())->greet();

        $afterPillar = array_count_values($registered);
        self::assertSame(1, $afterPillar[SecondGreeterInterface::class] ?? 0, 'the pillar container announced the binding again');
        self::assertSame(1, $afterPillar[GreeterInterface::class] ?? 0, 'the pillar container announced the definition again');
    }

    /**
     * @param callable(GacelaConfig):void $configure
     */
    private function bootstrapWith(callable $configure): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configure): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $configure($config);
        });
    }

    private function resetGacela(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
        InMemoryCache::resetCache();
        AbstractClassResolver::resetCache();
    }
}
