<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Setup;

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Gacela;
use Gacela\Framework\Setup\SetupGacela;
use Gacela\Framework\Setup\SetupGacelaFactory;
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomInterface;
use PHPUnit\Framework\TestCase;

final class SetupGacelaFactoryTest extends TestCase
{
    public function test_empty_array(): void
    {
        $actual = SetupGacelaFactory::fromArray([]);
        $expected = new SetupGacela();

        self::assertEquals($expected, $actual);
    }

    public function test_config(): void
    {
        $callable = static function (ConfigBuilder $builder): void {
            $builder->add('path', 'pathLocal');
        };

        $actual = SetupGacelaFactory::fromArray([
            Gacela::CONFIG => $callable,
        ]);

        $expected = (new SetupGacela())->setConfig($callable);

        self::assertEquals($expected, $actual);
    }

    public function test_mapping_interfaces(): void
    {
        $callable = static function (MappingInterfacesBuilder $builder): void {
            $builder->bind(CustomInterface::class, CustomClass::class);
        };

        $actual = SetupGacelaFactory::fromArray([
            Gacela::MAPPING_INTERFACES => $callable,
        ]);

        $expected = (new SetupGacela())->setMappingInterfaces($callable);

        self::assertEquals($expected, $actual);
    }

    public function test_suffix_types(): void
    {
        $callable = static function (SuffixTypesBuilder $builder): void {
            $builder->addFactory('F')->addConfig('C')->addDependencyProvider('DP');
        };

        $actual = SetupGacelaFactory::fromArray([
            Gacela::SUFFIX_TYPES => $callable,
        ]);

        $expected = (new SetupGacela())->setSuffixTypes($callable);

        self::assertEquals($expected, $actual);
    }
}
