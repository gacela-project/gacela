<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ScalarContextualBindings;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ScalarContextualBindings\Module\Factory;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->when(Factory::class)
                ->needs('$greeting')
                ->give('hola');
        });
    }

    public function test_scalar_contextual_binding_is_injected_when_resolving_the_factory(): void
    {
        self::assertSame('hola', (new Module\Facade())->greet());
    }
}
