<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\FileIoInterface;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigUsingGacelaPhpFileFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use Gacela\Framework\Event\GacelaEventInterface;
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomInterface;
use PHPUnit\Framework\TestCase;

final class GacelaConfigUsingGacelaPhpFileFactoryTest extends TestCase
{
    public function test_exception_when_the_class_does_not_return_a_callable(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(
            new class() {
            },
        );

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo,
        );

        $this->expectExceptionMessage('`gacela.php` file should return a `callable(GacelaConfig)`');
        $factory->createGacelaFileConfig();
    }

    public function test_gacela_file_using_callable_does_not_override_anything_then_use_defaults(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn(static fn (GacelaConfig $config) => $config);

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo,
        );

        self::assertEquals(new GacelaConfigFile(), $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_set_config(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(
            static fn (GacelaConfig $config) => $config->addAppConfig('custom-path.php', 'custom-path_local.php'),
        );

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo,
        );

        $expected = (new GacelaConfigFile())
            ->setConfigItems([new GacelaConfigItem('custom-path.php', 'custom-path_local.php', new PhpConfigReader())]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_set_mapping_interfaces(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(
            static function (GacelaConfig $config): void {
                $config->addExternalService('externalServiceKey', static fn () => 'externalServiceValue');
                self::assertSame('externalServiceValue', $config->getExternalService('externalServiceKey')->__invoke());
                $config->addBinding(CustomInterface::class, new CustomClass());
                $config->addBinding(CustomInterface::class, CustomClass::class);
            },
        );

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo,
        );

        $expected = (new GacelaConfigFile())
            ->setBindings([CustomInterface::class => CustomClass::class]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_set_suffix_types(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(
            static fn (GacelaConfig $config) => $config
                ->addSuffixTypeDependencyProvider('Binding'),
        );

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo,
        );

        $expected = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Factory' => ['Factory'],
                'Config' => ['Config'],
                'DependencyProvider' => ['DependencyProvider', 'Binding'],
                'Facade' => ['Facade'],
            ]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_override_setup_event_dispatcher_generic_listener(): void
    {
        $listenerDispatched = false;

        $listener = static function (GacelaEventInterface $event) use (&$listenerDispatched): void {
            self::assertInstanceOf(FakeEvent::class, $event);
            $listenerDispatched = true;
        };

        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(
            static function (GacelaConfig $config) use ($listener): void {
                $config->registerGenericListener($listener);
            },
        );

        $setup = new SetupGacela();
        $factory = new GacelaConfigUsingGacelaPhpFileFactory('gacelaPhpPath', $setup, $fileIo);

        self::assertInstanceOf(NullEventDispatcher::class, $setup->getEventDispatcher());
        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        $factory->createGacelaFileConfig();
        self::assertInstanceOf(ConfigurableEventDispatcher::class, $setup->getEventDispatcher());

        $setup->getEventDispatcher()->dispatch(new FakeEvent());
        self::assertTrue($listenerDispatched);
    }

    public function test_gacela_file_override_setup_event_dispatcher_specific_listener(): void
    {
        $listenerDispatched = false;

        $listener = static function (GacelaEventInterface $event) use (&$listenerDispatched): void {
            self::assertInstanceOf(FakeEvent::class, $event);
            $listenerDispatched = true;
        };

        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(
            static function (GacelaConfig $config) use ($listener): void {
                $config->registerSpecificListener(FakeEvent::class, $listener);
            },
        );

        $setup = new SetupGacela();
        $factory = new GacelaConfigUsingGacelaPhpFileFactory('gacelaPhpPath', $setup, $fileIo);

        self::assertInstanceOf(NullEventDispatcher::class, $setup->getEventDispatcher());
        $setup->getEventDispatcher()->dispatch(new FakeEvent());

        $factory->createGacelaFileConfig();
        self::assertInstanceOf(ConfigurableEventDispatcher::class, $setup->getEventDispatcher());

        $setup->getEventDispatcher()->dispatch(new FakeEvent());
        self::assertTrue($listenerDispatched);
    }
}
