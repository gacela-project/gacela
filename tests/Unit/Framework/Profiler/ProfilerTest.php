<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Profiler;

use Gacela\Framework\Profiler\Profiler;
use PHPUnit\Framework\TestCase;

final class ProfilerTest extends TestCase
{
    private Profiler $profiler;

    protected function setUp(): void
    {
        $this->profiler = Profiler::getInstance();
        $this->profiler->reset();
        $this->profiler->enable();
    }

    protected function tearDown(): void
    {
        $this->profiler->disable();
        $this->profiler->reset();
    }

    public function test_profiler_is_singleton(): void
    {
        $instance1 = Profiler::getInstance();
        $instance2 = Profiler::getInstance();

        self::assertSame($instance1, $instance2);
    }

    public function test_profiler_can_be_enabled_and_disabled(): void
    {
        $this->profiler->disable();
        self::assertFalse($this->profiler->isEnabled());

        $this->profiler->enable();
        self::assertTrue($this->profiler->isEnabled());
    }

    public function test_profiler_tracks_operations(): void
    {
        $this->profiler->start('test_operation', 'test_subject');
        usleep(1000); // Sleep for 1ms
        $this->profiler->stop('test_operation', 'test_subject');

        $entries = $this->profiler->getEntries();

        self::assertCount(1, $entries);
        self::assertSame('test_operation', $entries[0]->operation);
        self::assertSame('test_subject', $entries[0]->subject);
        self::assertGreaterThan(0, $entries[0]->duration);
    }

    public function test_profiler_tracks_multiple_operations(): void
    {
        $this->profiler->start('operation1', 'subject1');
        $this->profiler->stop('operation1', 'subject1');

        $this->profiler->start('operation2', 'subject2');
        $this->profiler->stop('operation2', 'subject2');

        $entries = $this->profiler->getEntries();

        self::assertCount(2, $entries);
    }

    public function test_profiler_generates_statistics(): void
    {
        $this->profiler->start('operation1', 'subject1');
        $this->profiler->stop('operation1', 'subject1');

        $this->profiler->start('operation1', 'subject2');
        $this->profiler->stop('operation1', 'subject2');

        $stats = $this->profiler->getStats();

        self::assertSame(2, $stats['total_operations']);
        self::assertGreaterThan(0, $stats['total_duration']);
        self::assertGreaterThan(0, $stats['avg_duration']);
        self::assertGreaterThan(0, $stats['peak_memory']);
        self::assertArrayHasKey('operation1', $stats['by_operation']);
        self::assertSame(2, $stats['by_operation']['operation1']['count']);
    }

    public function test_profiler_can_be_reset(): void
    {
        $this->profiler->start('operation1', 'subject1');
        $this->profiler->stop('operation1', 'subject1');

        self::assertCount(1, $this->profiler->getEntries());

        $this->profiler->reset();

        self::assertCount(0, $this->profiler->getEntries());
    }

    public function test_profiler_does_not_track_when_disabled(): void
    {
        $this->profiler->disable();

        $this->profiler->start('operation1', 'subject1');
        $this->profiler->stop('operation1', 'subject1');

        self::assertCount(0, $this->profiler->getEntries());
    }

    public function test_profiler_handles_missing_stop(): void
    {
        $this->profiler->start('operation1', 'subject1');
        // Intentionally not stopping

        $this->profiler->stop('operation2', 'subject2'); // Stop different operation

        self::assertCount(0, $this->profiler->getEntries());
    }
}
