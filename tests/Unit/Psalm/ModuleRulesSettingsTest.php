<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Psalm\ModuleRulesSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psalm\Exception\ConfigException;
use SimpleXMLElement;

final class ModuleRulesSettingsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFields(): iterable
    {
        yield 'plural child' => ['<moduleRules rootNamespace="App" file="rules.json"><sharedNamespaces/></moduleRules>', '<sharedNamespace>'];
        yield 'unknown child' => ['<moduleRules rootNamespace="App" file="rules.json"><mystery/></moduleRules>', '<mystery>'];
        yield 'unknown attribute' => ['<moduleRules rootNamespace="App" file="rules.json" files="typo"/>', 'files'];
        yield 'malformed depth' => ['<moduleRules rootNamespace="App" file="rules.json" modulePathSegments="2abc"/>', 'modulePathSegments'];
        yield 'empty depth' => ['<moduleRules rootNamespace="App" file="rules.json" modulePathSegments=""/>', 'modulePathSegments'];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_fail_loudly(string $xml, string $field): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage($field);

        ModuleRulesSettings::fromPluginConfig(new SimpleXMLElement('<pluginClass>' . $xml . '</pluginClass>'));
    }

    public function test_valid_fields_are_read(): void
    {
        $settings = ModuleRulesSettings::fromPluginConfig(new SimpleXMLElement(
            '<pluginClass><moduleRules rootNamespace="App" file="rules.json" modulePathSegments="2">'
            . '<sharedNamespace>App\Shared</sharedNamespace></moduleRules></pluginClass>',
        ));

        self::assertNotNull($settings);
        self::assertSame('App', $settings->rootNamespace);
        self::assertSame('rules.json', $settings->file);
        self::assertSame(2, $settings->modulePathSegments);
        self::assertSame(['App\Shared'], $settings->sharedNamespaces);
    }

    public function test_absent_configuration_stays_disabled(): void
    {
        self::assertNull(ModuleRulesSettings::fromPluginConfig(null));
        self::assertNull(ModuleRulesSettings::fromPluginConfig(new SimpleXMLElement('<pluginClass/>')));
    }
}
