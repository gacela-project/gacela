<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Plugins;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\Plugins\Module\Infrastructure\ExamplePluginWithConstructor;
use GacelaTest\Feature\Framework\Plugins\Module\Infrastructure\ExamplePluginWithoutConstructor;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function test_singleton_altered_via_plugin_with_constructor(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugin(ExamplePluginWithConstructor::class);
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from plugin ExamplePluginWithConstructor', $singleton->value());
    }

    public function test_singleton_altered_via_plugin_without_constructor(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugin(ExamplePluginWithoutConstructor::class);
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from plugin ExamplePluginWithoutConstructor', $singleton->value());
    }

    public function test_multiple_plugins_latest_win(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugins([
                ExamplePluginWithConstructor::class,
                ExamplePluginWithoutConstructor::class,
            ]);
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from plugin ExamplePluginWithoutConstructor', $singleton->value());
    }
}
