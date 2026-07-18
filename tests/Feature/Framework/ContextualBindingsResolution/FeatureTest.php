<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContextualBindingsResolution;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ContextualBindingsResolution\Module\Domain\DefaultGreeter;
use GacelaTest\Feature\Framework\ContextualBindingsResolution\Module\Domain\GreeterInterface;
use GacelaTest\Feature\Framework\ContextualBindingsResolution\Module\Domain\SpecialGreeter;
use GacelaTest\Feature\Framework\ContextualBindingsResolution\Module\Factory;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(GreeterInterface::class, DefaultGreeter::class);
            $config->when(Factory::class)
                ->needs(GreeterInterface::class)
                ->give(SpecialGreeter::class);
        });
    }

    public function test_contextual_binding_wins_over_global_binding_when_resolving_the_factory(): void
    {
        self::assertSame('hello from special', (new Module\Facade())->greet());
    }
}
