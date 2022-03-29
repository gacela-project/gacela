<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
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
}
