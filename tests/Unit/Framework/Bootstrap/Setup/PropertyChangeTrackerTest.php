<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\Setup\PropertyChangeTracker;
use PHPUnit\Framework\TestCase;

final class PropertyChangeTrackerTest extends TestCase
{
    public function test_unknown_property_is_not_changed(): void
    {
        $tracker = new PropertyChangeTracker();

        self::assertFalse($tracker->isChanged('unknown-property'));
    }

    public function test_marked_as_changed(): void
    {
        $tracker = new PropertyChangeTracker();
        $tracker->markAsChanged('prop');

        self::assertTrue($tracker->isChanged('prop'));
    }

    public function test_marked_as_unchanged_after_being_changed(): void
    {
        $tracker = new PropertyChangeTracker();
        $tracker->markAsChanged('prop');
        $tracker->markAsUnchanged('prop');

        self::assertFalse($tracker->isChanged('prop'));
    }

    public function test_get_all_reports_both_changed_and_unchanged_entries(): void
    {
        $tracker = new PropertyChangeTracker();
        $tracker->markAsChanged('a');
        $tracker->markAsUnchanged('b');

        self::assertSame(['a' => true, 'b' => false], $tracker->getAll());
    }
}
