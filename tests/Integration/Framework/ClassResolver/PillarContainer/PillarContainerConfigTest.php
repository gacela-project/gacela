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

    public function test_building_the_pillar_container_announces_no_second_registration(): void
    {
        $registered = [];

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$registered): void {
            $config->loadDefinitions([GreeterInterface::class => ['singleton' => FriendlyGreeter::class]]);
            $config->registerSpecificListener(
                BindingRegisteredEvent::class,
                static function (BindingRegisteredEvent $event) use (&$registered): void {
                    $registered[] = $event->id();
                },
            );
        });

        (new Facade())->greet();

        $counts = array_count_values($registered);

        self::assertSame(
            1,
            $counts[GreeterInterface::class] ?? 0,
            'applying the configuration to the pillar container announced it a second time',
        );
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
