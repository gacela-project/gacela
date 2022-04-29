<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\FileIoInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigUsingGacelaPhpFileFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use Gacela\Framework\Setup\SetupGacela;
use Gacela\Framework\Setup\SetupGacelaInterface;
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomInterface;
use PHPUnit\Framework\TestCase;

final class GacelaConfigUsingGacelaPhpFileFactoryTest extends TestCase
{
    public function test_exception_when_the_class_does_not_implements_setup_gacela_interface(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(new class() {});

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo
        );

        $this->expectErrorMessage('The gacela.php file should return an instance of SetupGacela');
        $factory->createGacelaFileConfig();
    }

    public function test_exception_when_is_callable_but_does_not_extends_abstract_config_gacela(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(static fn () => new class() {
        });

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo
        );

        $this->expectErrorMessage('The gacela.php file should return an instance of SetupGacela');
        $factory->createGacelaFileConfig();
    }

    public function test_gacela_file_does_not_override_anything_then_use_defaults(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('existsFile')->willReturn(true);
        $fileIo->method('include')->willReturn(static fn () => new SetupGacela());

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo
        );

        self::assertEquals(GacelaConfigFile::withDefaults(), $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_set_config(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(static fn () => (new SetupGacela())
            ->setConfig(
                static function (ConfigBuilder $configBuilder): void {
                    $configBuilder->add('custom-path.php', 'custom-path_local.php');
                },
            ));


        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo
        );

        $expected = GacelaConfigFile::withDefaults()
            ->setConfigItems([new GacelaConfigItem('custom-path.php', 'custom-path_local.php', new PhpConfigReader())]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_set_mapping_interfaces(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(
            (new SetupGacela())
                ->setExternalServices(['externalServiceKey' => 'externalServiceValue'])
                ->setMappingInterfaces(
                    static function (MappingInterfacesBuilder $mappingInterfacesBuilder, array $externalServices): void {
                        self::assertSame('externalServiceValue', $externalServices['externalServiceKey']);
                        $mappingInterfacesBuilder->bind(CustomInterface::class, new CustomClass());
                        $mappingInterfacesBuilder->bind(CustomInterface::class, CustomClass::class);
                    },
                )
        );

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo
        );

        $expected = GacelaConfigFile::withDefaults()
            ->setMappingInterfaces([CustomInterface::class => CustomClass::class]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_gacela_file_set_suffix_types(): void
    {
        $fileIo = $this->createStub(FileIoInterface::class);
        $fileIo->method('include')->willReturn(
            (new SetupGacela())
                ->setSuffixTypes(
                    static function (SuffixTypesBuilder $suffixTypesBuilder): void {
                        $suffixTypesBuilder->addDependencyProvider('Binding');
                    }
                )
        );

        $factory = new GacelaConfigUsingGacelaPhpFileFactory(
            'gacelaPhpPath',
            $this->createStub(SetupGacelaInterface::class),
            $fileIo
        );

        $expected = GacelaConfigFile::withDefaults()
            ->setSuffixTypes([
                'Factory' => ['Factory'],
                'Config' => ['Config'],
                'DependencyProvider' => ['DependencyProvider', 'Binding'],
                'Facade' => ['Facade'],
            ]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }
}
