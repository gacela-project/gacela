<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Plugins;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Exception\PluginException;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\Plugins\Module\Infrastructure\ExamplePluginWithConstructor;
use GacelaTest\Feature\Framework\Plugins\Module\Infrastructure\ExamplePluginWithInvokeArgs;
use GacelaTest\Feature\Framework\Plugins\Module\Infrastructure\ExamplePluginWithoutConstructor;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public static int $functionPluginRuns = 0;

    public function test_a_function_name_plugin_runs_once_with_the_application_container(): void
    {
        self::$functionPluginRuns = 0;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugin(__NAMESPACE__ . '\\recordFunctionPluginRun');
        });

        self::assertSame(1, self::$functionPluginRuns);
        self::assertSame('Set from function', Gacela::get(StringValue::class)?->value());
    }

    public function test_function_class_and_closure_plugins_keep_their_order(): void
    {
        self::$functionPluginRuns = 0;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugins([
                __NAMESPACE__ . '\\recordFunctionPluginRun',
                ExamplePluginWithConstructor::class,
                static function (Container $container): void {
                    self::assertSame('Set from plugin ExamplePluginWithConstructor', $container->getLocator()->get(StringValue::class)?->value());
                    $container->getLocator()->get(StringValue::class)?->setValue('Set from final closure');
                },
            ]);
        });

        self::assertSame(1, self::$functionPluginRuns);
        self::assertSame('Set from final closure', Gacela::get(StringValue::class)?->value());
    }

    public function test_a_missing_plugin_names_the_unresolvable_class(): void
    {
        $this->expectException(PluginException::class);
        $this->expectExceptionMessage('App\\MissingPlugin');

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugin('App\\MissingPlugin');
        });
    }

    public function test_a_non_invokable_plugin_names_the_class(): void
    {
        $this->expectException(PluginException::class);
        $this->expectExceptionMessage(StringValue::class);

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugin(StringValue::class);
        });
    }

    public function test_singleton_altered_via_plugin_with_constructor(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugin(ExamplePluginWithConstructor::class);
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from plugin ExamplePluginWithConstructor', $singleton->value());
    }

    public function test_singleton_altered_via_plugin_with_invoke_args(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugin(ExamplePluginWithInvokeArgs::class);
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from plugin ExamplePluginWithInvokeArgs', $singleton->value());
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

    public function test_singleton_altered_via_plugin_as_callable(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addPlugin(static function (Container $container): void {
                $string = $container->getLocator()->get(StringValue::class);
                $string?->setValue('Set from callable');
            });
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from callable', $singleton->value());
    }
}

function recordFunctionPluginRun(Container $container): void
{
    ++FeatureTest::$functionPluginRuns;
    $container->getLocator()->get(StringValue::class)?->setValue('Set from function');
}
