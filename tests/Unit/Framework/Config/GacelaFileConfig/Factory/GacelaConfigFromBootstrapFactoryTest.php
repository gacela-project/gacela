<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigFromBootstrapFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomInterface;
use PHPUnit\Framework\TestCase;

final class GacelaConfigFromBootstrapFactoryTest extends TestCase
{
    public function test_no_global_services_then_default(): void
    {
        $factory = new GacelaConfigFromBootstrapFactory(new SetupGacela());

        self::assertEquals(new GacelaConfigFile(), $factory->createGacelaFileConfig());
    }

    public function test_no_special_global_services_then_default(): void
    {
        $setupGacela = (new SetupGacela())->setExternalServices([
            'randomKey' => static fn () => 'randomValue',
        ]);

        $factory = new GacelaConfigFromBootstrapFactory($setupGacela);

        self::assertEquals(new GacelaConfigFile(), $factory->createGacelaFileConfig());
    }

    public function test_global_service_config(): void
    {
        $factory = new GacelaConfigFromBootstrapFactory(
            (new SetupGacela())->setAppConfigFn(
                static function (AppConfigBuilder $configBuilder): void {
                    $configBuilder->add('custom-path.php', 'custom-path_local.php');
                },
            ),
        );

        $expected = (new GacelaConfigFile())
            ->setConfigItems([new GacelaConfigItem('custom-path.php', 'custom-path_local.php', new PhpConfigReader())]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_global_service_mapping_interfaces_with_global_services(): void
    {
        $factory = new GacelaConfigFromBootstrapFactory(
            (new SetupGacela())
                ->setExternalServices(['externalServiceKey' => static fn () => 'externalServiceValue'])
                ->setBindingsFn(static function (
                    BindingsBuilder $interfacesBuilder,
                    array $externalServices,
                ): void {
                    self::assertSame($externalServices['externalServiceKey']->__invoke(), 'externalServiceValue');
                    $interfacesBuilder->bind(CustomInterface::class, CustomClass::class);
                }),
        );

        $expected = (new GacelaConfigFile())
            ->setBindings([CustomInterface::class => CustomClass::class]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }

    public function test_global_service_suffix_types(): void
    {
        $factory = new GacelaConfigFromBootstrapFactory(
            (new SetupGacela())
                ->setSuffixTypesFn(
                    static function (SuffixTypesBuilder $suffixTypesBuilder): void {
                        $suffixTypesBuilder->addDependencyProvider('DPCustom');
                    },
                ),
        );

        $expected = (new GacelaConfigFile())
            ->setSuffixTypes([
                'DependencyProvider' => ['DependencyProvider', 'DPCustom'],
                'Factory' => ['Factory'],
                'Config' => ['Config'],
                'Facade' => ['Facade'],
            ]);

        self::assertEquals($expected, $factory->createGacelaFileConfig());
    }
}
