<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event\Dispatcher;

use ArrayObject;
use Countable;
use Gacela\Framework\Event\Dispatcher\ApplicationEventDispatcher;
use Gacela\Framework\Event\Dispatcher\ConfigurableEventDispatcher;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\Event\Dispatcher\NullEventDispatcher;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ApplicationEventDispatcherTest extends TestCase
{
    public function test_the_inner_listeners_run_first_then_the_matching_attribute_ones(): void
    {
        $heard = [];
        $inner = new ConfigurableEventDispatcher();
        $inner->registerSpecificListener(ArrayObject::class, static function () use (&$heard): void {
            $heard[] = 'inner';
        });
        $listeners = [
            [Countable::class, static function () use (&$heard): void {
                $heard[] = 'by interface';
            }],
            [stdClass::class, static function () use (&$heard): void {
                $heard[] = 'unrelated';
            }],
        ];
        $dispatcher = new ApplicationEventDispatcher($inner, static fn (): array => $listeners);

        $dispatcher->dispatch(new ArrayObject());

        self::assertSame(['inner', 'by interface'], $heard);
    }

    public function test_an_attribute_listener_alone_is_reported_by_the_guard(): void
    {
        $dispatcher = new ApplicationEventDispatcher(new NullEventDispatcher(), static fn (): array => [
            [Countable::class, static function (): void {}],
        ]);

        self::assertTrue($dispatcher->hasListeners(ArrayObject::class));
        self::assertFalse($dispatcher->hasListeners(stdClass::class));
    }

    public function test_the_source_is_asked_once(): void
    {
        $asked = 0;
        $dispatcher = new ApplicationEventDispatcher(new NullEventDispatcher(), static function () use (&$asked): array {
            ++$asked;

            return [];
        });

        $dispatcher->hasListeners(ArrayObject::class);
        $dispatcher->dispatch(new stdClass());
        $dispatcher->dispatch(new stdClass());

        self::assertSame(1, $asked);
    }

    /**
     * Returning false from `hasListeners()` is how a dispatcher goes quiet.
     */
    public function test_an_inner_dispatcher_that_declines_is_not_dispatched_to(): void
    {
        $inner = new class() implements EventDispatcherInterface {
            public int $dispatched = 0;

            public function dispatch(object $event): void
            {
                ++$this->dispatched;
            }

            public function hasListeners(string $eventClass): bool
            {
                return false;
            }
        };

        (new ApplicationEventDispatcher($inner, static fn (): array => []))->dispatch(new stdClass());

        self::assertSame(0, $inner->dispatched);
    }
}
