<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use Gacela\Framework\Event\GacelaEventInterface;
use GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory\FakeEvent;
use PHPUnit\Framework\TestCase;

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
                }
            )
        );

        self::assertInstanceOf(ConfigurableEventDispatcher::class, $setup->getEventDispatcher());

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->registerSpecificListener(
                FakeEvent::class,
                static function (GacelaEventInterface $event) use (&$listenerDispatched2): void {
                    self::assertInstanceOf(FakeEvent::class, $event);
                    $listenerDispatched2 = true;
                }
            )
        );
        $setup->combine($setup2);

        self::assertFalse($listenerDispatched1);
        self::assertFalse($listenerDispatched2);
        $setup->getEventDispatcher()->dispatch(new FakeEvent());
        self::assertTrue($listenerDispatched1);
        self::assertTrue($listenerDispatched2);
    }

    public function test_combine_config_key_values(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->addAppConfigKeyValue('key1', 1)
        );

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->addAppConfigKeyValues(['key2' => 'value2'])
        );

        $setup->combine($setup2);

        self::assertSame([
            'key1' => 1,
            'key2' => 'value2',
        ], $setup->getConfigKeyValues());
    }

    public function test_combine_project_namespaces(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->setProjectNamespaces(['App1'])
        );

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())->setProjectNamespaces(['App2'])
        );

        $setup->combine($setup2);

        self::assertSame(['App1', 'App2'], $setup->getProjectNamespaces());
    }

    public function test_override_file_cache_settings(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->setFileCacheEnabled(false)
                ->setFileCacheDirectory('original/dir')
        );

        $setup2 = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->setFileCacheEnabled(true)
                ->setFileCacheDirectory('override/dir')
        );

        self::assertFalse($setup->isFileCacheEnabled());
        self::assertSame('original/dir', $setup->getFileCacheDirectory());

        $setup->combine($setup2);

        self::assertTrue($setup->isFileCacheEnabled());
        self::assertSame('override/dir', $setup->getFileCacheDirectory());
    }

    public function test_not_override_file_cache_settings_when_using_default(): void
    {
        $setup = SetupGacela::fromGacelaConfig(
            (new GacelaConfig())
                ->setFileCacheEnabled(true)
                ->setFileCacheDirectory('original/dir')
        );

        $setup2 = SetupGacela::fromGacelaConfig(new GacelaConfig());

        self::assertTrue($setup->isFileCacheEnabled());
        self::assertSame('original/dir', $setup->getFileCacheDirectory());

        $setup->combine($setup2);

        self::assertTrue($setup->isFileCacheEnabled());
        self::assertSame('original/dir', $setup->getFileCacheDirectory());
    }
}
