<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\Setup\GacelaConfigExtender;
use PHPUnit\Framework\TestCase;

final class GacelaConfigExtenderTest extends TestCase
{
    public function test_applies_every_registered_config_extension(): void
    {
        $gacelaConfig = (new GacelaConfig())
            ->extendGacelaConfigs([FirstConfigExtension::class, SecondConfigExtension::class]);

        (new GacelaConfigExtender())->extend($gacelaConfig);

        self::assertSame(
            ['first' => 'from-first', 'second' => 'from-second'],
            $gacelaConfig->toTransfer()->configKeyValues,
        );
    }

    public function test_skips_class_names_that_are_not_invokable(): void
    {
        $gacelaConfig = (new GacelaConfig())
            ->extendGacelaConfigs([NotInvokableConfigExtension::class, FirstConfigExtension::class]);

        (new GacelaConfigExtender())->extend($gacelaConfig);

        self::assertSame(['first' => 'from-first'], $gacelaConfig->toTransfer()->configKeyValues);
    }

    public function test_extending_nothing_leaves_the_config_untouched(): void
    {
        $gacelaConfig = new GacelaConfig();

        (new GacelaConfigExtender())->extend($gacelaConfig);

        self::assertNull($gacelaConfig->toTransfer()->configKeyValues);
    }
}

final class FirstConfigExtension
{
    public function __invoke(GacelaConfig $config): void
    {
        $config->addAppConfigKeyValue('first', 'from-first');
    }
}

final class SecondConfigExtension
{
    public function __invoke(GacelaConfig $config): void
    {
        $config->addAppConfigKeyValue('second', 'from-second');
    }
}

final class NotInvokableConfigExtension
{
}
