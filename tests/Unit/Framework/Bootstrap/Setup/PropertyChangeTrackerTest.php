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

    public function test_properties_are_tracked_independently(): void
    {
        $tracker = new PropertyChangeTracker();
        $tracker->markAsChanged('a');
        $tracker->markAsUnchanged('b');

        self::assertTrue($tracker->isChanged('a'));
        self::assertFalse($tracker->isChanged('b'));
    }
}
