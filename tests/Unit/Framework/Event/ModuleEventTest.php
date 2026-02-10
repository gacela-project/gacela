<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Event;

use Gacela\Framework\Event\ModuleEvent;
use PHPUnit\Framework\TestCase;

final class ModuleEventTest extends TestCase
{
    public function test_get_name_returns_class_name(): void
    {
        $event = new TestModuleEvent();

        self::assertSame(TestModuleEvent::class, $event->getName());
    }

    public function test_get_timestamp_returns_creation_time(): void
    {
        $before = microtime(true);
        $event = new TestModuleEvent();
        $after = microtime(true);

        $timestamp = $event->getTimestamp();

        self::assertGreaterThanOrEqual($before, $timestamp);
        self::assertLessThanOrEqual($after, $timestamp);
    }

    public function test_to_string_includes_name_and_timestamp(): void
    {
        $event = new TestModuleEvent();

        $string = $event->toString();

        self::assertStringContainsString(TestModuleEvent::class, $string);
        self::assertStringContainsString('timestamp:', $string);
    }

    public function test_timestamps_are_unique_for_different_instances(): void
    {
        $event1 = new TestModuleEvent();
        usleep(100);
        $event2 = new TestModuleEvent();

        self::assertNotSame($event1->getTimestamp(), $event2->getTimestamp());
    }

    public function test_module_event_can_carry_data(): void
    {
        $event = new TestModuleEventWithData('test data', 123);

        self::assertSame('test data', $event->message);
        self::assertSame(123, $event->count);
    }
}

final class TestModuleEvent extends ModuleEvent
{
}

final class TestModuleEventWithData extends ModuleEvent
{
    public function __construct(
        public readonly string $message,
        public readonly int $count,
    ) {
        parent::__construct();
    }
}
