<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendConfig;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ExtendConfig\Module\Infrastructure\BindingStringValue;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function test_binding_class(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->extendGacelaConfig(BindingStringValue::class);
        });

        /** @var StringValue $singleton */
        $singleton = Gacela::get(StringValue::class);

        self::assertSame('Set from BindingStringValue', $singleton->value());
    }
}
