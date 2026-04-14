<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap;

use ArrayObject;
use Fixtures\CustomGacelaConfig;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use Gacela\Framework\Event\GacelaEventInterface;
use GacelaTest\Feature\Framework\Plugins\Module\Infrastructure\ExamplePluginWithConstructor;
use GacelaTest\Feature\Framework\Plugins\Module\Infrastructure\ExamplePluginWithoutConstructor;
use GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory\FakeEvent;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SetupGacelaTest extends TestCase
{
    public function test_null_event_dispatcher(): void
    {
        $config = new GacelaConfig();
        $setup = SetupGacela::fromGacelaConfig($config);

        self::assertInstanceOf(NullEventDispatcher::class, $setup->getEventDispatcher());
        $setup->getEventDispatcher()->dispatch(new FakeEvent());
    }

    public function test_combine_event_dispatcher(): void
    {
        $listenerDispatched1 = false;
        $listenerDispatched2 = false;

        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->registerSpecificListener(
                FakeEvent::class,
                static function (GacelaEventInterface $event) use (&$listenerDispatched1): void {
                    self::assertInstanceOf(FakeEvent::class, $event);
                    $listenerDispatched1 = true;
                },
            ),
        );

        self::assertInstanceOf(ConfigurableEventDispatcher::class, $setup->getEventDispatcher());

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->registerSpecificListener(
                FakeEvent::class,
                static function (GacelaEventInterface $event) use (&$listenerDispatched2): void {
                    self::assertInstanceOf(FakeEvent::class, $event);
                    $listenerDispatched2 = true;
                },
            ),
        );
        $setup->merge($setup2);

        self::assertFalse($listenerDispatched1);
        self::assertFalse($listenerDispatched2);

        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertTrue($listenerDispatched1);
        self::assertTrue($listenerDispatched2);
    }

    public function test_combine_config_key_values(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->addAppConfigKeyValue('key1', 1),
        );

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->addAppConfigKeyValues(['key2' => 'value2']),
        );

        $setup->merge($setup2);

        self::assertSame([
            'key1' => 1,
            'key2' => 'value2',
        ], $setup->getConfigKeyValues());
    }

    public function test_combine_project_namespaces(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->setProjectNamespaces(['App1']),
        );

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->setProjectNamespaces(['App2']),
        );

        $setup->merge($setup2);

        self::assertSame(['App1', 'App2'], $setup->getProjectNamespaces());
    }

    public function test_override_file_cache_settings(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->setFileCache(false, 'original/dir'),
        );

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->setFileCache(true, 'override/dir'),
        );

        self::assertFalse($setup->isFileCacheEnabled());
        self::assertSame('original/dir', $setup->getFileCacheDirectory());

        $setup->merge($setup2);

        self::assertTrue($setup->isFileCacheEnabled());
        self::assertSame('override/dir', $setup->getFileCacheDirectory());
    }

    public function test_not_override_file_cache_settings_when_using_default(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->setFileCache(true, 'original/dir'),
        );

        $setup2 = SetupGacela::fromGacelaConfig(new GacelaConfig());

        self::assertTrue($setup->isFileCacheEnabled());
        self::assertSame('original/dir', $setup->getFileCacheDirectory());

        $setup->merge($setup2);

        self::assertTrue($setup->isFileCacheEnabled());
        self::assertSame('original/dir', $setup->getFileCacheDirectory());
    }

    public function test_override_reset_in_memory_cache(): void
    {
        $setup = SetupGacela::fromGacelaConfig(new GacelaConfig());

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->resetInMemoryCache(),
        );

        self::assertFalse($setup->shouldResetInMemoryCache());
        $setup->merge($setup2);
        self::assertTrue($setup->shouldResetInMemoryCache());
    }

    public function test_combine_external_services(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->addExternalService('service1', static fn (): int => 1),
        );

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->addExternalService('service2', static fn (): int => 2)
                ->addExternalService('service3', new stdClass()),
        );

        self::assertEquals([
            'service1' => static fn (): int => 1,
        ], $setup->externalServices());

        $setup->merge($setup2);

        self::assertEquals([
            'service1' => static fn (): int => 1,
            'service2' => static fn (): int => 2,
            'service3' => new stdClass(),
        ], $setup->externalServices());
    }

    public function test_combine_extend_service(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->extendService('service', static fn (ArrayObject $ao) => $ao->append(1)),
        );

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->extendService('service', static fn (ArrayObject $ao) => $ao->append(2))
                ->extendService('service-2', static fn (ArrayObject $ao) => $ao->append(3)),
        );

        $setup->merge($setup2);

        self::assertEquals([
            'service' => [
                static fn (ArrayObject $ao) => $ao->append(1),
                static fn (ArrayObject $ao) => $ao->append(2),
            ],
            'service-2' => [
                static fn (ArrayObject $ao) => $ao->append(3),
            ],
        ], $setup->getServicesToExtend());
    }

    public function test_plugins(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->addPlugin(ExamplePluginWithoutConstructor::class),
        );
        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->addPlugin(ExamplePluginWithConstructor::class),
        );

        $setup->merge($setup2);

        self::assertSame([
            ExamplePluginWithoutConstructor::class,
            ExamplePluginWithConstructor::class,
        ], $setup->getPlugins());
    }

    public function test_gacela_configs_to_extends(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->extendGacelaConfigs([CustomGacelaConfig::class]),
        );
        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->extendGacelaConfig(CustomGacelaConfig::class),
        );

        $setup->merge($setup2);

        self::assertSame([
            CustomGacelaConfig::class,
        ], $setup->getGacelaConfigsToExtend());
    }

    public function test_register_generic_listener_multiple_times(): void
    {
        $listener1 = static function (GacelaEventInterface $event): void {};
        $listener2 = static function (GacelaEventInterface $event): void {};

        $config = (new GacelaConfig())
            ->registerGenericListener($listener1)
            ->registerGenericListener($listener2);

        $transfer = $config->toTransfer();

        self::assertSame([$listener1, $listener2], $transfer->genericListeners);
    }

    public function test_register_specific_listener_multiple_times(): void
    {
        $listener1 = static function (GacelaEventInterface $event): void {};
        $listener2 = static function (GacelaEventInterface $event): void {};

        $config = (new GacelaConfig())
            ->registerSpecificListener(FakeEvent::class, $listener1)
            ->registerSpecificListener(FakeEvent::class, $listener2);

        $transfer = $config->toTransfer();

        self::assertSame([$listener1, $listener2], $transfer->specificListeners[FakeEvent::class]);
    }

    public function test_extend_service_multiple_times(): void
    {
        $service1 = static fn (mixed $s): mixed => $s;
        $service2 = static fn (mixed $s): mixed => $s;

        $config = (new GacelaConfig())
            ->extendService('service1', $service1)
            ->extendService('service1', $service2);

        $transfer = $config->toTransfer();

        self::assertSame([$service1, $service2], $transfer->servicesToExtend['service1']);
    }

    public function test_add_plugins_merges_with_existing(): void
    {
        $config = (new GacelaConfig())
            ->addPlugin(ExamplePluginWithoutConstructor::class)
            ->addPlugins([ExamplePluginWithConstructor::class]);

        $transfer = $config->toTransfer();

        self::assertSame([
            ExamplePluginWithoutConstructor::class,
            ExamplePluginWithConstructor::class,
        ], $transfer->plugins);
    }

    public function test_extend_gacela_configs_merges_with_existing(): void
    {
        $config = (new GacelaConfig())
            ->extendGacelaConfig(CustomGacelaConfig::class)
            ->extendGacelaConfigs([CustomGacelaConfig::class]);

        $transfer = $config->toTransfer();

        self::assertCount(2, $transfer->gacelaConfigsToExtend);
    }

    public function test_merge_gacela_configs_to_extend_keeps_both_when_distinct(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->extendGacelaConfig(CustomGacelaConfig::class),
        );
        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->extendGacelaConfig(\GacelaTest\Unit\Framework\Bootstrap\AnotherSetupFixtureConfig::class),
        );

        $setup->merge($setup2);

        self::assertSame(
            [
                CustomGacelaConfig::class,
                \GacelaTest\Unit\Framework\Bootstrap\AnotherSetupFixtureConfig::class,
            ],
            $setup->getGacelaConfigsToExtend(),
        );
    }

    public function test_merge_does_not_override_gacela_configs_when_other_has_no_changes(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->extendGacelaConfig(CustomGacelaConfig::class),
        );
        $setup2 = new SetupGacela();

        $setup->merge($setup2);

        self::assertSame([CustomGacelaConfig::class], $setup->getGacelaConfigsToExtend());
    }

    public function test_is_property_changed_resets_when_setter_called_with_null(): void
    {
        $setup = new SetupGacela();
        $setup->setProjectNamespaces(['App']);

        self::assertTrue($setup->isPropertyChanged(SetupGacela::projectNamespaces));

        $setup->setProjectNamespaces(null);

        self::assertFalse($setup->isPropertyChanged(SetupGacela::projectNamespaces));
    }

    public function test_disable_event_listeners_prevents_dispatcher_creation(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->disableEventListeners()
                ->registerGenericListener(static function (): void {
                }),
        );

        self::assertFalse($setup->canCreateEventDispatcher());
    }

    public function test_add_services_to_extend_appends_all_given_items(): void
    {
        $a = static fn (mixed $s): mixed => $s;
        $b = static fn (mixed $s): mixed => $s;
        $c = static fn (mixed $s): mixed => $s;

        $setup = new SetupGacela();
        $setup->addServicesToExtend('svc', [$a]);
        $setup->addServicesToExtend('svc', [$b, $c]);

        self::assertSame([$a, $b, $c], $setup->getServicesToExtend()['svc']);
    }

    public function test_merge_services_to_extend_combines_multiple_extensions_per_service(): void
    {
        $fn1 = static fn (mixed $s): mixed => $s;
        $fn2 = static fn (mixed $s): mixed => $s;
        $fn3 = static fn (mixed $s): mixed => $s;

        $setup1 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->extendService('service', $fn1),
        );
        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->extendService('service', $fn2)
                ->extendService('service', $fn3),
        );

        $setup1->merge($setup2);

        self::assertSame([$fn1, $fn2, $fn3], $setup1->getServicesToExtend()['service']);
    }

    public function test_merge_event_dispatcher_when_other_has_only_specific_listeners(): void
    {
        $genericListener = static function (GacelaEventInterface $event): void {};
        $specificListener = static function (GacelaEventInterface $event): void {};

        $setup1 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->registerGenericListener($genericListener),
        );
        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->registerSpecificListener(FakeEvent::class, $specificListener),
        );

        $setup1->merge($setup2);

        $setup1->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertInstanceOf(ConfigurableEventDispatcher::class, $setup1->getEventDispatcher());
    }

    public function test_merge_registers_generic_listeners_from_other(): void
    {
        $genericFromOther = false;
        $otherListener = static function (GacelaEventInterface $event) use (&$genericFromOther): void {
            $genericFromOther = true;
        };

        $setup1 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->registerSpecificListener(FakeEvent::class, static function (): void {
            }),
        );
        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->registerGenericListener($otherListener),
        );

        $setup1->merge($setup2);
        $setup1->getEventDispatcher()->dispatch(new FakeEvent());

        self::assertTrue($genericFromOther, 'generic listener from `$other` must be registered and fire on dispatch');
    }
}

final class AnotherSetupFixtureConfig
{
}
