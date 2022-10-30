<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\EventListener\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\EventListener\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\EventListener\ClassResolver\ResolvedClassTriedFromParentEvent;
use Gacela\Framework\EventListener\ClassResolver\ResolvedCreatedDefaultClassEvent;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DisableListenersTest extends TestCase
{
    public function test_disable_class_resolver_listener(): void
    {
        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->disableEventListeners();

            $config->registerListener(ResolvedClassCachedEvent::class, [$this, 'throwExceptionListener']);
            $config->registerListener(ResolvedClassCreatedEvent::class, [$this, 'throwExceptionListener']);
            $config->registerListener(ResolvedClassTriedFromParentEvent::class, [$this, 'throwExceptionListener']);
            $config->registerListener(ResolvedCreatedDefaultClassEvent::class, [$this, 'throwExceptionListener']);
        });

        $facade = new Module\Facade();
        $facade->doString();

        $facade = new Module\Facade();
        $facade->doString();

        $this->expectNotToPerformAssertions();
    }

    public function throwExceptionListener(): void
    {
        throw new RuntimeException('This should never be called');
    }
}
