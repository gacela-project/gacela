<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\RebootstrapWithDifferentConfig;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_second_bootstrap_serves_its_own_bindings(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(GreeterInterface::class, English::class);
        });

        self::assertSame('hello', Gacela::container()->get(GreeterInterface::class)->hi());

        // Deliberately without resetInMemoryCache(): a second bootstrap is
        // expected to honour the config it was given, not the first one's.
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addBinding(GreeterInterface::class, Spanish::class);
        });

        self::assertSame('hola', Gacela::container()->get(GreeterInterface::class)->hi());
    }

    public function test_repeated_bootstrap_with_the_same_setup_still_hits_the_memo(): void
    {
        $configFn = static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(GreeterInterface::class, English::class);
        };

        Gacela::bootstrap(__DIR__, $configFn);
        Gacela::bootstrap(__DIR__, $configFn);

        self::assertSame('hello', Gacela::container()->get(GreeterInterface::class)->hi());
    }
}
