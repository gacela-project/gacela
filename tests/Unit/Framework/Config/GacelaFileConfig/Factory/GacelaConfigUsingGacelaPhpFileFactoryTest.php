<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\FileIoInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigUsingGacelaPhpFileFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use GacelaTest\Unit\Framework\Fixtures\CustomClass;
use GacelaTest\Unit\Framework\Fixtures\CustomInterface;
use PHPUnit\Framework\TestCase;

final class GacelaConfigUsingGacelaPhpFileFactoryTest extends TestCase
{
    public function test_exception_when_include_gacela_file_is_not_callable(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn('anything-but-not-callable');

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            ['globalServiceKey' => 'globalServiceValue'],
            $fileIo
        );

        $this->expectErrorMessage('Create a function that returns an anonymous class that extends AbstractConfigGacela');
        $factory->createGacelaFileConfig();
    }

    public function test_exception_when_is_callable_but_does_not_extends_abstract_config_gacela(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(static fn () => new class() {
        });

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
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
        $fileIo->method('include')->willReturn(static fn () => new class() extends AbstractConfigGacela {
        });

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            ['globalServiceKey' => 'globalServiceValue'],
            $fileIo
        );

        self::assertEquals(GacelaConfigFile::withDefaults(), $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_set_config(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(static fn () => new class() extends AbstractConfigGacela {
            public function config(ConfigBuilder $configBuilder): void
            {
                $configBuilder->add('custom-path.php', 'custom-path_local.php');
            }
        });

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            ['globalServiceKey' => 'globalServiceValue'],
            $fileIo
        );

        $expected = GacelaConfigFile::withDefaults()
            ->setConfigItems([new GacelaConfigItem('custom-path.php', 'custom-path_local.php', new PhpConfigReader())]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_set_mapping_interfaces(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(static fn () => new class() extends AbstractConfigGacela {
            public function mappingInterfaces(
                MappingInterfacesBuilder $mappingInterfacesBuilder,
                array $globalServices
            ): void {
                $mappingInterfacesBuilder->bind(CustomInterface::class, new CustomClass());
                $mappingInterfacesBuilder->bind(CustomInterface::class, CustomClass::class);
            }
        });

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            ['globalServiceKey' => 'globalServiceValue'],
            $fileIo
        );

        $expected = GacelaConfigFile::withDefaults()
            ->setMappingInterfaces([CustomInterface::class => CustomClass::class]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_set_suffix_types(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(static fn () => new class() extends AbstractConfigGacela {
            public function suffixTypes(SuffixTypesBuilder $suffixTypesBuilder): void
            {
                $suffixTypesBuilder->addDependencyProvider('Binding');
            }
        });

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            ['globalServiceKey' => 'globalServiceValue'],
            $fileIo
        );

        $expected = GacelaConfigFile::withDefaults()
            ->setSuffixTypes([
                'Factory' => ['Factory'],
                'Config' => ['Config'],
                'DependencyProvider' => ['DependencyProvider', 'Binding'],
            ]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }
}
