<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\FileIoInterface;
use Gacela\Framework\Config\GacelaConfigArgs\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigArgs\MappingInterfacesBuilder;
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
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(false);

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            ['globalServiceKey' => 'globalServiceValue'],
            $fileIo
        );

        self::assertEquals(GacelaConfigFile::withDefaults(), $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_does_not_exists_but_global_services(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(false);

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            [
                'config' => function (ConfigBuilder $configResolver): void {
                    $configResolver->add(PhpConfigReader::class, 'custom-path.php', 'custom-path_local.php');
                },
                'mapping-interfaces' => function (
                    MappingInterfacesBuilder $interfacesResolver,
                    array $globalServices
                ): void {
                    assert($globalServices['globalServiceKey'] === 'globalServiceValue');
                    $interfacesResolver->bind(CustomInterface::class, CustomClass::class);
                },
                'suffix-types' => function (SuffixTypesResolver $suffixTypesResolver): void {
                    $suffixTypesResolver->addDependencyProvider('DPCustom');
                },
                'globalServiceKey' => 'globalServiceValue',
            ],
            $fileIo
        );

        $expected = (new GacelaConfigFile())
            ->setConfigItems([new GacelaConfigItem('custom-path.php', 'custom-path_local.php', new PhpConfigReader())])
            ->setMappingInterfaces([CustomInterface::class => CustomClass::class])
            ->setSuffixTypes([
                'DependencyProvider' => ['DependencyProvider', 'DPCustom'],
                'Factory' => ['Factory'],
                'Config' => ['Config'],
            ]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_exception_when_include_gacela_file_is_not_callable(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn('anything-but-not-callable');

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            ['globalServiceKey' => 'globalServiceValue'],
            $fileIo
        );

        $this->expectErrorMessage('Create a function that returns an anonymous class that extends AbstractConfigGacela');
        $factory->createGacelaFileConfig();
    }

    public function test_exception_when_gacela_file_is_callable_but_does_not_extends_abstract_config_gacela(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn(fn () => new class () {
        });

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            ['globalServiceKey' => 'globalServiceValue'],
            $fileIo
        );

        $this->expectErrorMessage('Your anonymous class must extends AbstractConfigGacela');
        $factory->createGacelaFileConfig();
    }

    public function test_gacela_file_does_not_override_anything_then_use_defaults(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn(fn () => new class () extends AbstractConfigGacela {
        });

        $factory = new GacelaConfigFileFactory(
            'appRootDir',
            'gacelaPhpConfigFilename',
            ['globalServiceKey' => 'globalServiceValue'],
            $fileIo
        );

        self::assertEquals(GacelaConfigFile::withDefaults(), $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_overrides_config_items(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn(fn () => new class () extends AbstractConfigGacela {
            public function config(ConfigBuilder $configBuilder): void
            {
                $configBuilder->add(PhpConfigReader::class, 'custom-path.php', 'custom-path_local.php');
            }

            public function mappingInterfaces(
                MappingInterfacesBuilder $mappingInterfacesBuilder,
                array $globalServices
            ): void {
                $mappingInterfacesBuilder->bind(CustomInterface::class, new CustomClass());
                $mappingInterfacesBuilder->bind(CustomInterface::class, CustomClass::class);
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
            $fileIo
        );

        $expected = (new GacelaConfigFile())
            ->setConfigItems([new GacelaConfigItem('custom-path.php', 'custom-path_local.php', new PhpConfigReader())])
            ->setMappingInterfaces([CustomInterface::class => CustomClass::class])
            ->setSuffixTypes([
                'Factory' => ['Factory'],
                'Config' => ['Config'],
                'DependencyProvider' => ['DependencyProvider', 'Binding'],
            ]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }
}
