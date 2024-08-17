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
            ->setBindings([
                CustomInterface::class => CustomClass::class,
            ]);
        $configFile2 = (new GacelaConfigFile())
            ->setBindings([
                CustomInterface::class => new CustomClass(),
            ]);

        $actual = $configFile1->combine($configFile2);

        $expected = (new GacelaConfigFile())
            ->setBindings([
                CustomInterface::class => new CustomClass(),
            ]);

        self::assertEquals($expected, $actual);
    }

    public function test_combine_different_mapping_interfaces(): void
    {
        $configFile1 = (new GacelaConfigFile())
            ->setBindings([
                AbstractCustom::class => CustomClass::class,
            ]);
        $configFile2 = (new GacelaConfigFile())
            ->setBindings([
                CustomInterface::class => new CustomClass(),
            ]);

        $actual = $configFile1->combine($configFile2);

        $expected = (new GacelaConfigFile())
            ->setBindings([
                AbstractCustom::class => CustomClass::class,
                CustomInterface::class => new CustomClass(),
            ]);

        self::assertEquals($expected, $actual);
    }

    public function test_combine_duplicated_suffix_types(): void
    {
        $configFile1 = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Facade' => ['FA'],
                'Factory' => ['F'],
                'Config' => ['C'],
                'Provider' => ['DP'],
            ]);
        $configFile2 = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Facade' => ['FA'],
                'Factory' => ['F'],
                'Config' => ['C'],
                'Provider' => ['DP'],
            ]);

        $actual = $configFile1->combine($configFile2);

        $expected = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Facade' => ['FA'],
                'Factory' => ['F'],
                'Config' => ['C'],
                'Provider' => ['DP'],
            ]);

        self::assertEquals($expected, $actual);
    }

    public function test_combine_different_suffix_types(): void
    {
        $configFile1 = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Facade' => ['FA1'],
                'Factory' => ['F1'],
                'Config' => ['C1'],
                'Provider' => ['DP1'],
            ]);
        $configFile2 = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Facade' => ['FA2'],
                'Factory' => ['F2'],
                'Config' => ['C2'],
                'Provider' => ['DP2'],
            ]);

        $actual = $configFile1->combine($configFile2);

        $expected = (new GacelaConfigFile())
            ->setSuffixTypes([
                'Facade' => ['FA1', 'FA2'],
                'Factory' => ['F1', 'F2'],
                'Config' => ['C1', 'C2'],
                'Provider' => ['DP1', 'DP2'],
            ]);

        self::assertEquals($expected, $actual);
    }
}
