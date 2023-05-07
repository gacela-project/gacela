<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Plugins;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\Plugins\Module\Infrastructure\ExampleAfterPluginWithConstructor;
use GacelaTest\Feature\Framework\Plugins\Module\Infrastructure\ExampleAfterPluginWithoutConstructor;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;

final class AfterFeatureTest extends TestCase
{
    public function test_singleton_altered_via_plugin_with_constructor(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAfterPlugin(ExampleAfterPluginWithConstructor::class);
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from plugin ExamplePluginWithConstructor', $singleton->value());
    }

    public function test_singleton_altered_via_plugin_without_constructor(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAfterPlugin(ExampleAfterPluginWithoutConstructor::class);
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from plugin ExamplePluginWithoutConstructor', $singleton->value());
    }

    public function test_multiple_plugins_latest_win(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAfterPlugins([
                ExampleAfterPluginWithConstructor::class,
                ExampleAfterPluginWithoutConstructor::class,
            ]);
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from plugin ExamplePluginWithoutConstructor', $singleton->value());
    }
}
