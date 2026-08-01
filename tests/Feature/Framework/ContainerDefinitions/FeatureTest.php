<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerDefinitions;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\Container\BindingRegisteredEvent;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ContainerDefinitions\Notifying\EmailNotifier;
use GacelaTest\Feature\Framework\ContainerDefinitions\Notifying\NotifierInterface;
use GacelaTest\Feature\Framework\ContainerDefinitions\Notifying\SmsNotifier;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * `loadDefinitions()` is the data-shaped counterpart of `addBinding()`: it hands
 * Gacela a description of the wiring rather than a closure that performs it,
 * which is what generated, shared or reviewed wiring needs.
 *
 * Each test bootstraps its own application, because the point being pinned is
 * what a *different* set of definitions produces.
 */
final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        (new ReflectionClass(Gacela::class))->getMethod('resetCache')->invoke(null);
    }

    public function test_a_definitions_array_wires_a_module(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->loadDefinitions([
                NotifierInterface::class => EmailNotifier::class,
            ]);
        });

        self::assertSame('email', (new Greeting\Facade())->notifierName());
    }

    public function test_a_definitions_file_wires_a_module(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->loadDefinitions(__DIR__ . '/services.json');
        });

        self::assertSame('sms', (new Greeting\Facade())->notifierName());
    }

    /**
     * App-wide, matching how `addBinding()` already behaves: a module that never
     * mentions the definition still resolves it.
     */
    public function test_the_definitions_reach_every_module_container(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->loadDefinitions([
                NotifierInterface::class => EmailNotifier::class,
            ]);
        });

        self::assertSame('email', (new Greeting\Facade())->notifierName());
        self::assertSame('email', (new Billing\Facade())->notifierName());
    }

    /**
     * The layering the feature exists for: load the base set, then an
     * environment-specific one that overrides part of it.
     */
    public function test_a_later_source_overrides_an_earlier_one(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->loadDefinitions([NotifierInterface::class => EmailNotifier::class]);
            $config->loadDefinitions([NotifierInterface::class => SmsNotifier::class]);
        });

        self::assertSame('sms', (new Greeting\Facade())->notifierName());
    }

    /**
     * Data is applied after code, so an override file wins over a binding
     * declared in `gacela.php` -- otherwise the override file could not do the
     * one job it is loaded for.
     */
    public function test_definitions_override_an_imperative_binding(): void
    {
        $this->bootstrapWith(static function (GacelaConfig $config): void {
            $config->addBinding(NotifierInterface::class, EmailNotifier::class);
            $config->loadDefinitions([NotifierInterface::class => SmsNotifier::class]);
        });

        self::assertSame('sms', (new Greeting\Facade())->notifierName());
    }

    /**
     * Pins the documented gap rather than hiding it: a definition registers a
     * binding but does not announce one, because naming what a source
     * registered means reconstructing it from the container's registries and
     * the aliases are not in them. Fails loudly if that ever becomes reportable.
     */
    public function test_a_definition_does_not_announce_a_binding_registration(): void
    {
        $registered = [];

        $this->bootstrapWith(static function (GacelaConfig $config) use (&$registered): void {
            $config->registerSpecificListener(
                BindingRegisteredEvent::class,
                static function (BindingRegisteredEvent $event) use (&$registered): void {
                    $registered[] = $event->id();
                },
            );
            $config->loadDefinitions([NotifierInterface::class => EmailNotifier::class]);
        });

        (new Greeting\Facade())->notifierName();

        self::assertNotContains(NotifierInterface::class, $registered);
    }

    private function bootstrapWith(callable $configure): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($configure): void {
            $config->resetInMemoryCache();
            $configure($config);
        });
    }
}
