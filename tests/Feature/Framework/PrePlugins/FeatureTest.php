<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PrePlugins;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Locator;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\PrePlugins\Module\Infrastructure\ExamplePlugin;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function test_singleton_altered_via_pre_plugin(): void
    {
        Locator::addSingleton(StringValue::class, new StringValue('original'));

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->prePlugins([
                ExamplePlugin::class,
            ]);
        });

        $singleton = Locator::getSingleton(StringValue::class);

        self::assertSame('updated from plugin', $singleton->value());
    }
}
