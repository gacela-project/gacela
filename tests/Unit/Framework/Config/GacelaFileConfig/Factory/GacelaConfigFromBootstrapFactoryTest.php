<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigFromBootstrapFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use GacelaTest\Unit\Framework\Fixtures\CustomClass;
use GacelaTest\Unit\Framework\Fixtures\CustomInterface;
use PHPUnit\Framework\TestCase;

final class GacelaConfigFromBootstrapFactoryTest extends TestCase
{
    public function test_no_global_services_then_default(): void
    {
        $factory = new GacelaConfigFromBootstrapFactory([]);

        self::assertEquals(GacelaConfigFile::withDefaults(), $factory->createGacelaFileConfig());
    }

    public function test_no_special_global_services_then_default(): void
    {
        $factory = new GacelaConfigFromBootstrapFactory([
            'randomKey' => 'randomValue',
        ]);

        self::assertEquals(GacelaConfigFile::withDefaults(), $factory->createGacelaFileConfig());
    }

    public function test_global_service_config(): void
    {
        $factory = new GacelaConfigFromBootstrapFactory([
            'config' => function (ConfigBuilder $configBuilder): void {
                $configBuilder->add(PhpConfigReader::class, 'custom-path.php', 'custom-path_local.php');
            },
        ]);

        $expected = GacelaConfigFile::withDefaults()
            ->setConfigItems([new GacelaConfigItem('custom-path.php', 'custom-path_local.php', new PhpConfigReader())]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_global_service_mapping_interfaces_with_global_services(): void
    {
        $factory = new GacelaConfigFromBootstrapFactory([
            'mapping-interfaces' => function (
                MappingInterfacesBuilder $interfacesBuilder,
                array $globalServices
            ): void {
                self::assertSame($globalServices['globalServiceKey'], 'globalServiceValue');
                $interfacesBuilder->bind(CustomInterface::class, CustomClass::class);
            },
            'globalServiceKey' => 'globalServiceValue',
        ]);

        $expected = GacelaConfigFile::withDefaults()
            ->setMappingInterfaces([CustomInterface::class => CustomClass::class]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_global_service_suffix_types(): void
    {
        $factory = new GacelaConfigFromBootstrapFactory([
            'suffix-types' => function (SuffixTypesBuilder $suffixTypesBuilder): void {
                $suffixTypesBuilder->addDependencyProvider('DPCustom');
            },
        ]);

        $expected = GacelaConfigFile::withDefaults()
            ->setSuffixTypes([
                'DependencyProvider' => ['DependencyProvider', 'DPCustom'],
                'Factory' => ['Factory'],
                'Config' => ['Config'],
            ]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }
}
