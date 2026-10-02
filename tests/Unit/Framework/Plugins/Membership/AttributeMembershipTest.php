<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins\Membership;

use ArrayObject;
use Countable;
use Gacela\Framework\Plugins\Membership\AttributeMembership;
use Gacela\Framework\Plugins\Membership\Members;
use Gacela\Framework\Plugins\Membership\PluginMember;
use Gacela\Framework\Plugins\Membership\TagMember;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AttributeMembershipTest extends TestCase
{
    protected function tearDown(): void
    {
        AttributeMembership::resetCache();
    }

    public function test_declared_plugins_come_first_and_are_not_repeated(): void
    {
        $plugins = AttributeMembership::pluginsOf(Countable::class, [ArrayObject::class], 'app', static fn (): Members => new Members([
            new PluginMember(Countable::class, ArrayObject::class, 0),
            new PluginMember(Countable::class, self::class, 0),
        ]));

        self::assertSame([ArrayObject::class, self::class], $plugins);
    }

    /**
     * Without this, the first resolution throws and every later one quietly
     * returns the declared members only.
     */
    public function test_a_scan_that_throws_is_retried_on_the_next_ask(): void
    {
        try {
            AttributeMembership::pluginsOf(Countable::class, [], 'app', static fn (): Members => throw new RuntimeException('unreadable class'));
            self::fail('The scan error was swallowed.');
        } catch (RuntimeException) {
        }

        $plugins = AttributeMembership::pluginsOf(Countable::class, [], 'app', static fn (): Members => new Members([
            new PluginMember(Countable::class, ArrayObject::class, 0),
        ]));

        self::assertSame([ArrayObject::class], $plugins);
    }

    public function test_the_members_are_loaded_once_per_scope(): void
    {
        $loads = 0;
        $load = static function () use (&$loads): Members {
            ++$loads;

            return new Members();
        };

        AttributeMembership::pluginsOf(Countable::class, [], 'app', $load);
        AttributeMembership::classesTagged('exporters', 'app', $load);

        self::assertSame(1, $loads);
    }

    /**
     * A second bootstrap in one process, of another application or with other
     * module paths, must not be answered with the first one's members.
     */
    public function test_another_scope_loads_its_own_members(): void
    {
        AttributeMembership::pluginsOf(Countable::class, [], 'first app', static fn (): Members => new Members([
            new PluginMember(Countable::class, ArrayObject::class, 0),
        ]));

        $plugins = AttributeMembership::pluginsOf(Countable::class, [], 'second app', static fn (): Members => new Members());

        self::assertSame([], $plugins);
    }

    public function test_the_classes_of_a_tag_come_in_the_order_scanned(): void
    {
        $classes = AttributeMembership::classesTagged('exporters', 'app', static fn (): Members => new Members([], [
            new TagMember('exporters', ArrayObject::class),
            new TagMember('reports', self::class),
            new TagMember('exporters', self::class),
        ]));

        self::assertSame([ArrayObject::class, self::class], $classes);
    }

    public function test_another_scope_loads_its_own_tags(): void
    {
        AttributeMembership::classesTagged('exporters', 'first app', static fn (): Members => new Members([], [
            new TagMember('exporters', ArrayObject::class),
        ]));

        self::assertSame([], AttributeMembership::classesTagged('exporters', 'second app', static fn (): Members => new Members()));
    }
}
