<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Config\ConfigFactory;

use Gacela\Framework\Config\ConfigFactory;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use Gacela\Framework\Setup\SetupGacela;
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomInterface;
use PHPUnit\Framework\TestCase;

final class ConfigFactoryTest extends TestCase
{
    public function test_empty_setup_then_default_gacela_config_file(): void
    {
        $bootstrapSetup = new SetupGacela();

        $actual = (new ConfigFactory(__DIR__ . '/WithoutGacelaFile', $bootstrapSetup))
            ->createGacelaConfigFileFactory()
            ->createGacelaFileConfig();

        $expected = GacelaConfigFile::withDefaults();

        self::assertEquals($expected, $actual);
    }

    public function test_only_gacela_file_exists(): void
    {
        $bootstrapSetup = new SetupGacela();

        $actual = (new ConfigFactory(__DIR__ . '/WithGacelaFile', $bootstrapSetup))
            ->createGacelaConfigFileFactory()
            ->createGacelaFileConfig();

        $expected = (new GacelaConfigFile())
            ->setConfigItems([
                new GacelaConfigItem('config/from-gacela.php', ''),
            ])
            ->setMappingInterfaces([
                CustomInterface::class => CustomClass::class,
            ])
            ->setSuffixTypes([
                'Factory' => ['Factory', 'Fact'],
                'Config' => ['Config', 'Conf'],
                'DependencyProvider' => ['DependencyProvider', 'DepPro'],
            ]);

        self::assertEquals($expected, $actual);
    }

    public function test_only_bootstrap_setup_gacela_exists(): void
    {
        $bootstrapSetup = (new SetupGacela())
            ->setConfig(static function (ConfigBuilder $builder): void {
                $builder->add('config/from-bootstrap.php');
            })
            ->setGlobalServices(['CustomClass' => CustomClass::class])
            ->setMappingInterfaces(
                static function (MappingInterfacesBuilder $mappingInterfacesBuilder, array $globalServices): void {
                    $mappingInterfacesBuilder->bind(CustomInterface::class, $globalServices['CustomClass']);
                },
            )
            ->setSuffixTypes(static function (SuffixTypesBuilder $suffixTypesBuilder): void {
                $suffixTypesBuilder
                    ->addFactory('Fact')
                    ->addConfig('Conf')
                    ->addDependencyProvider('DepPro');
            });

        $actual = (new ConfigFactory(__DIR__ . '/WithoutGacelaFile', $bootstrapSetup))
            ->createGacelaConfigFileFactory()
            ->createGacelaFileConfig();

        $expected = (new GacelaConfigFile())
            ->setConfigItems([
                new GacelaConfigItem('config/from-bootstrap.php', ''),
            ])
            ->setMappingInterfaces([
                CustomInterface::class => CustomClass::class,
            ])
            ->setSuffixTypes([
                'Factory' => ['Factory', 'Fact'],
                'Config' => ['Config', 'Conf'],
                'DependencyProvider' => ['DependencyProvider', 'DepPro'],
            ]);

        self::assertEquals($expected, $actual);
    }
}
