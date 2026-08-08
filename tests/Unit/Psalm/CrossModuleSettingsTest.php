<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Psalm\CrossModuleSettings;
use PHPUnit\Framework\TestCase;
use Psalm\Exception\ConfigException;
use SimpleXMLElement;

final class CrossModuleSettingsTest extends TestCase
{
    /**
     * No `<crossModule>` is the rule staying off -- the same default as the
     * commented-out block in `phpstan-gacela.neon`.
     */
    public function test_a_plugin_element_without_cross_module_configures_nothing(): void
    {
        self::assertNull(CrossModuleSettings::fromPluginConfig(new SimpleXMLElement('<pluginClass/>')));
    }

    public function test_no_plugin_element_at_all_configures_nothing(): void
    {
        self::assertNull(CrossModuleSettings::fromPluginConfig(null));
    }

    public function test_it_reads_the_root_namespace(): void
    {
        $settings = $this->settings('<crossModule rootNamespace="App\Modules"/>');

        self::assertSame('App\Modules', $settings->rootNamespace);
    }

    public function test_the_module_depth_defaults_to_one_segment(): void
    {
        self::assertSame(1, $this->settings('<crossModule rootNamespace="App\Modules"/>')->modulePathSegments);
    }

    public function test_it_reads_the_module_depth(): void
    {
        $settings = $this->settings('<crossModule rootNamespace="App\Modules" modulePathSegments="2"/>');

        self::assertSame(2, $settings->modulePathSegments);
    }

    public function test_a_class_with_no_shared_namespaces_has_none(): void
    {
        self::assertSame([], $this->settings('<crossModule rootNamespace="App\Modules"/>')->sharedNamespaces);
    }

    public function test_it_reads_every_shared_namespace(): void
    {
        $settings = $this->settings(
            '<crossModule rootNamespace="App\Modules">'
            . '<sharedNamespace>App\Modules\Shared</sharedNamespace>'
            . '<sharedNamespace>App\Modules\Kernel</sharedNamespace>'
            . '</crossModule>',
        );

        self::assertSame(['App\Modules\Shared', 'App\Modules\Kernel'], $settings->sharedNamespaces);
    }

    /**
     * Whitespace around an xml value is formatting, not part of the namespace.
     */
    public function test_it_trims_what_it_reads(): void
    {
        $settings = $this->settings(
            '<crossModule rootNamespace=" App\\Modules ">'
            . "<sharedNamespace>\n    App\\Modules\\Shared\n</sharedNamespace>"
            . '</crossModule>',
        );

        self::assertSame('App\Modules', $settings->rootNamespace);
        self::assertSame(['App\Modules\Shared'], $settings->sharedNamespaces);
    }

    public function test_an_empty_shared_namespace_is_not_a_namespace(): void
    {
        $settings = $this->settings(
            '<crossModule rootNamespace="App\Modules"><sharedNamespace>  </sharedNamespace></crossModule>',
        );

        self::assertSame([], $settings->sharedNamespaces);
    }

    /**
     * The whole point of failing here: a rule that quietly does nothing is worse
     * than no rule, because it reads as a green check and nothing would ever say
     * the boundary went unchecked.
     */
    public function test_a_cross_module_without_a_root_namespace_fails_loudly(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('<crossModule> needs a rootNamespace');

        $this->settings('<crossModule/>');
    }

    public function test_a_blank_root_namespace_fails_loudly(): void
    {
        $this->expectException(ConfigException::class);

        $this->settings('<crossModule rootNamespace="   "/>');
    }

    public function test_an_explicit_depth_of_one_is_accepted(): void
    {
        $settings = $this->settings('<crossModule rootNamespace="App\Modules" modulePathSegments="1"/>');

        self::assertSame(1, $settings->modulePathSegments);
    }

    /**
     * Whitespace is not a value, so it means the same as leaving the attribute
     * out -- not a depth of zero, which would throw.
     */
    public function test_a_blank_module_depth_is_the_default(): void
    {
        $settings = $this->settings('<crossModule rootNamespace="App\Modules" modulePathSegments="  "/>');

        self::assertSame(1, $settings->modulePathSegments);
    }

    public function test_a_module_depth_below_one_fails_loudly(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('modulePathSegments must be a positive number of namespace segments, got: 0');

        $this->settings('<crossModule rootNamespace="App\Modules" modulePathSegments="0"/>');
    }

    public function test_a_negative_module_depth_fails_loudly(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('got: -1');

        $this->settings('<crossModule rootNamespace="App\Modules" modulePathSegments="-1"/>');
    }

    /**
     * `(int)` turns anything unparseable into 0, which is caught -- but only
     * because the cast happens before the comparison.
     */
    public function test_a_module_depth_that_is_not_a_number_fails_loudly(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('got: two');

        $this->settings('<crossModule rootNamespace="App\Modules" modulePathSegments="two"/>');
    }

    private function settings(string $crossModuleXml): CrossModuleSettings
    {
        $settings = CrossModuleSettings::fromPluginConfig(
            new SimpleXMLElement('<pluginClass>' . $crossModuleXml . '</pluginClass>'),
        );

        self::assertNotNull($settings);

        return $settings;
    }
}
