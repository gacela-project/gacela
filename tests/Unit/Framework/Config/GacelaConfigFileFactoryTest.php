<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\ConfigGacelaMapperInterface;
use Gacela\Framework\Config\FileIoInterface;
use Gacela\Framework\Config\GacelaConfigArgs\MappingInterfacesResolver;
use Gacela\Framework\Config\GacelaConfigArgs\SuffixTypesResolver;
use Gacela\Framework\Config\GacelaConfigFileFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use GacelaTest\Unit\Framework\Fixtures\CustomClass;
use GacelaTest\Unit\Framework\Fixtures\CustomInterface;
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
        $suffixTypesResolver = [
            'DependencyProvider' => ['DPCustom'],
        ];

        $gacelaConfigFile = (new GacelaConfigFile())
            ->setConfigItems([new GacelaConfigItem('path.php', 'path_local.php')])
            ->setMappingInterfaces(['interface' => 'concrete'])
            ->setSuffixTypes($suffixTypesResolver);

        $configGacelaMapper = $this->createStub(ConfigGacelaMapperInterface::class);
        $configGacelaMapper->method('mapConfigItems')->willReturn([$gacelaConfigFile]);

        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(false);

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            [
                'config' => ['anything'],
                'mapping-interfaces' => function (MappingInterfacesResolver $interfacesResolver): void {
                    $interfacesResolver->bind('interface', 'concrete');
                },
                'override-resolvable-types' => function (
                    SuffixTypesResolver $resolvableTypesConfig,
                    array $globalServices
                ): void {
                    assert($globalServices['globalServiceKey'] === 'globalServiceValue');
                    $resolvableTypesConfig->addDependencyProvider('DPCustom');
                },
                'globalServiceKey' => 'globalServiceValue',
            ],
            $configGacelaMapper,
            $fileIo
        );

        $expected = (new GacelaConfigFile())
            ->setConfigItems([$gacelaConfigFile])
            ->setMappingInterfaces(['interface' => 'concrete'])
            ->setSuffixTypes([
                'DependencyProvider' => ['DependencyProvider', 'DPCustom'],
                'Factory' => ['Factory'],
                'Config' => ['Config'],
            ]);

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
            ->setSuffixTypes(['DependencyProvider' => 'Binding']);

        $configGacelaMapper = $this->createStub(ConfigGacelaMapperInterface::class);
        $configGacelaMapper->method('mapConfigItems')->willReturn([$gacelaConfigFile]);

        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn(fn () => new class () extends AbstractConfigGacela {
            public function config(): array
            {
                return ['anything'];
            }

            public function mappingInterfaces(
                MappingInterfacesResolver $interfacesResolver,
                array $globalServices
            ): void {
                $interfacesResolver->bind(CustomInterface::class, new CustomClass());
                $interfacesResolver->bind(CustomInterface::class, CustomClass::class);
            }

            public function suffixTypes(SuffixTypesResolver $suffixTypesResolver): void
            {
                $suffixTypesResolver->addDependencyProvider('Binding');
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
            ->setMappingInterfaces([CustomInterface::class => CustomClass::class])
            ->setSuffixTypes([
                'Factory' => ['Factory'],
                'Config' => ['Config'],
                'DependencyProvider' => ['DependencyProvider', 'Binding'],
            ]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }
}
