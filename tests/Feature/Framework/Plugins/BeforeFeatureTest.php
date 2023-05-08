<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Plugins;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\Plugins\Module\Infrastructure\ExampleBeforePlugin;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;

final class BeforeFeatureTest extends TestCase
{
    public function test_binding_class_on_before_plugin(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBeforePlugin(ExampleBeforePlugin::class);
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from plugin ExampleBeforePlugin', $singleton->value());
    }
}
