<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\AbstractFactory;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\AbstractFactory\SharedApp\AlphaClockHolder;
use GacelaTest\Integration\Framework\AbstractFactory\SharedApp\AlphaFactory;
use GacelaTest\Integration\Framework\AbstractFactory\SharedApp\BetaFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_count_values;

/**
 * Every module gets a scope of **one** app container, not a container of its
 * own built from the same configuration.
 *
 * The point of the shared parent is that `gacela.php` is walked once rather
 * than once per Factory class. Registering the app-wide bindings is the walk,
 * and `BindingRegisteredEvent` is how it announces itself -- so counting those
 * events across two modules is the observable form of "walked once".
 */
final class SharedAppContainerTest extends TestCase
{
    protected function tearDown(): void
    {
        (new ReflectionClass(Gacela::class))->getMethod('resetCache')->invoke(null);
    }

    public function test_the_app_configuration_is_registered_once_for_every_module(): void
    {
        $registered = [];

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$registered): void {
            $config->resetInMemoryCache();
            $config->addBinding(SharedApp\ClockInterface::class, SharedApp\SystemClock::class);
            $config->registerSpecificListener(
                BindingRegisteredEvent::class,
                static function (BindingRegisteredEvent $event) use (&$registered): void {
                    $registered[] = $event->id();
                },
            );
        });

        // Two modules, each building its own container.
        (new AlphaFactory())->clock();
        (new BetaFactory())->clock();

        $counts = array_count_values($registered);

        self::assertSame(
            1,
            $counts[SharedApp\ClockInterface::class] ?? 0,
            'the app-wide binding was registered more than once, so the configuration is being '
            . 'walked per module instead of shared',
        );
    }

    public function test_each_module_still_resolves_the_app_wide_binding(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(SharedApp\ClockInterface::class, SharedApp\SystemClock::class);
        });

        // Sharing the parent must not cost a module its access to the binding.
        self::assertInstanceOf(SharedApp\SystemClock::class, (new AlphaFactory())->clock());
        self::assertInstanceOf(SharedApp\SystemClock::class, (new BetaFactory())->clock());
    }

    public function test_a_module_factory_inherits_app_wide_resolution_hooks(): void
    {
        $resolved = 0;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$resolved): void {
            $config->resetInMemoryCache();
            $config->addBinding(SharedApp\ClockInterface::class, SharedApp\SystemClock::class);
            $config->afterResolving(AlphaClockHolder::class, static function () use (&$resolved): void {
                ++$resolved;
            });
        });

        (new AlphaFactory())->clock();

        self::assertSame(1, $resolved);
    }
}
