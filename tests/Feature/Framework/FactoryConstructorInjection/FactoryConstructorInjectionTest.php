<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FactoryConstructorInjection;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\FactoryConstructorInjection\Module\ClockInterface;
use GacelaTest\Feature\Framework\FactoryConstructorInjection\Module\Facade;
use GacelaTest\Feature\Framework\FactoryConstructorInjection\Module\Factory;
use GacelaTest\Feature\Framework\FactoryConstructorInjection\Module\FrozenClock;
use PHPUnit\Framework\TestCase;

/**
 * A Factory may declare its dependencies in its constructor.
 *
 * This was an open question on #523 -- the assumption being that a Factory
 * cannot be constructor-injected *because* the container resolves it. It is the
 * opposite: `AbstractClassResolver::createInstance()` resolves every pillar
 * through the container, so autowiring applies to the Factory itself.
 *
 * It works, it is untested, and an untested capability is one refactor away
 * from being an undocumented regression.
 */
final class FactoryConstructorInjectionTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(ClockInterface::class, FrozenClock::class);
        });
    }

    public function test_a_factory_constructor_dependency_is_autowired(): void
    {
        self::assertSame('stamp@2026-01-01', (new Facade())->stamp());
    }

    public function test_the_resolved_factory_is_the_module_factory(): void
    {
        self::assertInstanceOf(Factory::class, (new Facade())->getFactory());
    }
}
