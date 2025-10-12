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
}
