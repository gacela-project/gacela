<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use GacelaTest\Fixtures\AbstractCustom;
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomInterface;
use PHPUnit\Framework\TestCase;

final class GacelaConfigFileTest extends TestCase
{
    public function test_combine_empty(): void
    {
        $configFile1 = new GacelaConfigFile();
        $configFile2 = new GacelaConfigFile();
        $actual = $configFile1->combine($configFile2);

        $expected = new GacelaConfigFile();

        self::assertEquals($expected, $actual);
    }

    public function test_combine_config_items(): void
    {
        $configFile1 = (new GacelaConfigFile())
            ->setConfigItems([
                new GacelaConfigItem('path1'),
            ]);
        $configFile2 = (new GacelaConfigFile())
            ->setConfigItems([
                new GacelaConfigItem('path2'),
            ]);

        $actual = $configFile1->combine($configFile2);

        $expected = (new GacelaConfigFile())
            ->setConfigItems([
                new GacelaConfigItem('path1'),
                new GacelaConfigItem('path2'),
            ]);

        self::assertEquals($expected, $actual);
    }

    public function test_combine_duplicated_mapping_interfaces(): void
    {
        $configFile1 = (new GacelaConfigFile())
            ->setMappingInterfaces([
                CustomInterface::class => CustomClass::class,
            ]);
        $configFile2 = (new GacelaConfigFile())
            ->setMappingInterfaces([
                CustomInterface::class => new CustomClass(),
            ]);

        $actual = $configFile1->combine($configFile2);

        $expected = (new GacelaConfigFile())
            ->setMappingInterfaces([
                CustomInterface::class => new CustomClass(),
            ]);

        self::assertEquals($expected, $actual);
    }

    public function test_combine_different_mapping_interfaces(): void
    {
        $configFile1 = (new GacelaConfigFile())
            ->setMappingInterfaces([
                AbstractCustom::class => CustomClass::class,
            ]);
        $configFile2 = (new GacelaConfigFile())
            ->setMappingInterfaces([
                CustomInterface::class => new CustomClass(),
            ]);

        $actual = $configFile1->combine($configFile2);

        $expected = (new GacelaConfigFile())
            ->setMappingInterfaces([
                AbstractCustom::class => CustomClass::class,
                CustomInterface::class => new CustomClass(),
            ]);

        self::assertEquals($expected, $actual);
    }

    public function test_combine_duplicated_suffix_types(): void
    {
        $configFile1 = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Factory' => ['F'],
                'Config' => ['C'],
                'DependencyProvider' => ['DP'],
            ]);
        $configFile2 = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Factory' => ['F'],
                'Config' => ['C'],
                'DependencyProvider' => ['DP'],
            ]);

        $actual = $configFile1->combine($configFile2);

        $expected = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Factory' => ['F'],
                'Config' => ['C'],
                'DependencyProvider' => ['DP'],
            ]);

        self::assertEquals($expected, $actual);
    }

    public function test_combine_different_suffix_types(): void
    {
        $configFile1 = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Factory' => ['F1'],
                'Config' => ['C1'],
                'DependencyProvider' => ['DP1'],
            ]);
        $configFile2 = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Factory' => ['F2'],
                'Config' => ['C2'],
                'DependencyProvider' => ['DP2'],
            ]);

        $actual = $configFile1->combine($configFile2);

        $expected = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Factory' => ['F1', 'F2'],
                'Config' => ['C1', 'C2'],
                'DependencyProvider' => ['DP1', 'DP2'],
            ]);

        self::assertEquals($expected, $actual);
    }
}
