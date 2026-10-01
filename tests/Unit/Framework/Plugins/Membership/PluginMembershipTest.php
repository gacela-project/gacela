<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Plugins\Membership;

use ArrayObject;
use Countable;
use Gacela\Framework\Plugins\Membership\PluginMember;
use Gacela\Framework\Plugins\Membership\PluginMembership;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PluginMembershipTest extends TestCase
{
    protected function tearDown(): void
    {
        PluginMembership::resetCache();
    }

    public function test_declared_plugins_come_first_and_are_not_repeated(): void
    {
        $plugins = PluginMembership::withMembers(Countable::class, [ArrayObject::class], 'app', static fn (): array => [
            new PluginMember(Countable::class, ArrayObject::class, 0),
            new PluginMember(Countable::class, self::class, 0),
        ]);

        self::assertSame([ArrayObject::class, self::class], $plugins);
    }

    /**
     * Without this, the first resolution throws and every later one quietly
     * returns the declared members only.
     */
    public function test_a_scan_that_throws_is_retried_on_the_next_ask(): void
    {
        try {
            PluginMembership::withMembers(Countable::class, [], 'app', static fn (): array => throw new RuntimeException('unreadable class'));
            self::fail('The scan error was swallowed.');
        } catch (RuntimeException) {
        }

        $plugins = PluginMembership::withMembers(Countable::class, [], 'app', static fn (): array => [
            new PluginMember(Countable::class, ArrayObject::class, 0),
        ]);

        self::assertSame([ArrayObject::class], $plugins);
    }

    public function test_the_members_are_loaded_once_per_scope(): void
    {
        $loads = 0;
        $load = static function () use (&$loads): array {
            ++$loads;

            return [];
        };

        PluginMembership::withMembers(Countable::class, [], 'app', $load);
        PluginMembership::withMembers(Countable::class, [], 'app', $load);

        self::assertSame(1, $loads);
    }

    /**
     * A second bootstrap in one process, of another application or with other
     * module paths, must not be answered with the first one's members.
     */
    public function test_another_scope_loads_its_own_members(): void
    {
        PluginMembership::withMembers(Countable::class, [], 'first app', static fn (): array => [
            new PluginMember(Countable::class, ArrayObject::class, 0),
        ]);

        $plugins = PluginMembership::withMembers(Countable::class, [], 'second app', static fn (): array => []);

        self::assertSame([], $plugins);
    }
}
