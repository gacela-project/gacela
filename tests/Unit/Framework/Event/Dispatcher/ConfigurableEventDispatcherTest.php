<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Dispatcher;

use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use PHPUnit\Framework\TestCase;

final class ConfigurableEventDispatcherTest extends TestCase
{
    public function test_has_no_listeners_when_nothing_registered(): void
    {
        $dispatcher = new ConfigurableEventDispatcher();

        self::assertFalse($dispatcher->hasListeners(ResolvedClassCachedEvent::class));
    }

    public function test_has_no_listeners_when_registered_generic_listeners_are_empty(): void
    {
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerGenericListeners([]);

        self::assertFalse($dispatcher->hasListeners(ResolvedClassCachedEvent::class));
    }

    public function test_has_listeners_for_any_event_class_when_generic_listener_registered(): void
    {
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerGenericListeners([static function (): void {}]);

        self::assertTrue($dispatcher->hasListeners(ResolvedClassCachedEvent::class));
        self::assertTrue($dispatcher->hasListeners(ClassNameNotFoundEvent::class));
    }

    public function test_has_listeners_only_for_the_registered_specific_event_class(): void
    {
        $dispatcher = new ConfigurableEventDispatcher();
        $dispatcher->registerSpecificListener(ClassNameNotFoundEvent::class, static function (): void {});

        self::assertTrue($dispatcher->hasListeners(ClassNameNotFoundEvent::class));
        self::assertFalse($dispatcher->hasListeners(ResolvedClassCachedEvent::class));
    }
}
