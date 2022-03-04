<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\ConfigGacelaMapperInterface;
use Gacela\Framework\Config\FileIoInterface;
use Gacela\Framework\Config\GacelaConfigFileFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use PHPUnit\Framework\TestCase;

final class GacelaConfigFileFactoryTest extends TestCase
{
    public function test_gacela_file_does_not_exists_then_use_defaults(): void
    {
        $configGacelaMapper = $this->createStub(ConfigGacelaMapperInterface::class);
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(false);

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            ['globalServiceKey' => 'globalServiceValue'],
            $configGacelaMapper,
            $fileIo
        );

        self::assertEquals(GacelaConfigFile::withDefaults(), $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_does_not_exists_but_global_services(): void
    {
        $mappingInterfaces = ['interface' => 'concrete'];
        $overrideResolvableTypes = ['DependencyProvider' => 'Binding'];

        $gacelaConfigFile = (new GacelaConfigFile())
            ->setConfigItems([new GacelaConfigItem('path.php', 'path_local.php')])
            ->setMappingInterfaces($mappingInterfaces)
            ->setOverrideResolvableTypes($overrideResolvableTypes);

        $configGacelaMapper = $this->createStub(ConfigGacelaMapperInterface::class);
        $configGacelaMapper->method('mapConfigItems')->willReturn([$gacelaConfigFile]);

        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(false);

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            [
                'config' => ['anything'],
                'mapping-interfaces' => $mappingInterfaces,
                'override-resolvable-types' => $overrideResolvableTypes,
            ],
            $configGacelaMapper,
            $fileIo
        );

        $expected = (new GacelaConfigFile())
            ->setConfigItems([$gacelaConfigFile])
            ->setMappingInterfaces($mappingInterfaces)
            ->setOverrideResolvableTypes($overrideResolvableTypes);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_exception_when_include_gacela_file_is_not_callable(): void
    {
        $configGacelaMapper = $this->createStub(ConfigGacelaMapperInterface::class);
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn('anything-but-not-callable');

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            ['globalServiceKey' => 'globalServiceValue'],
            $configGacelaMapper,
            $fileIo
        );

        $this->expectErrorMessage('Create a function that returns an anonymous class that extends AbstractConfigGacela');
        $factory->createGacelaFileConfig();
    }

    public function test_exception_when_gacela_file_is_callable_but_does_not_extends_abstract_config_gacela(): void
    {
        $configGacelaMapper = $this->createStub(ConfigGacelaMapperInterface::class);
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn(fn () => new class () {
        });

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            ['globalServiceKey' => 'globalServiceValue'],
            $configGacelaMapper,
            $fileIo
        );

        $this->expectErrorMessage('Your anonymous class must extends AbstractConfigGacela');
        $factory->createGacelaFileConfig();
    }

    public function test_gacela_file_does_not_override_anything_then_use_defaults(): void
    {
        $configGacelaMapper = $this->createStub(ConfigGacelaMapperInterface::class);
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn(fn () => new class () extends AbstractConfigGacela {
        });

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            ['globalServiceKey' => 'globalServiceValue'],
            $configGacelaMapper,
            $fileIo
        );

        self::assertEquals(GacelaConfigFile::withDefaults(), $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_overrides_config_items(): void
    {
        $gacelaConfigFile = (new GacelaConfigFile())
            ->setConfigItems([new GacelaConfigItem('path.php', 'path_local.php')])
            ->setMappingInterfaces(['interface' => 'concrete'])
            ->setOverrideResolvableTypes(['DependencyProvider' => 'Binding']);

        $configGacelaMapper = $this->createStub(ConfigGacelaMapperInterface::class);
        $configGacelaMapper->method('mapConfigItems')->willReturn([$gacelaConfigFile]);

        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn(fn () => new class () extends AbstractConfigGacela {
            public function config(): array
            {
                return ['anything'];
            }

            public function mappingInterfaces(array $globalServices): array
            {
                return ['interface' => 'concrete'];
            }

            public function overrideResolvableTypes(): array
            {
                return ['DependencyProvider' => 'Binding'];
            }
        });

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            ['globalServiceKey' => 'globalServiceValue'],
            $configGacelaMapper,
            $fileIo
        );

        $expected = (new GacelaConfigFile())
            ->setConfigItems([$gacelaConfigFile])
            ->setMappingInterfaces(['interface' => 'concrete'])
            ->setOverrideResolvableTypes(['DependencyProvider' => 'Binding']);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }
}
