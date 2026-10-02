<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\DebugPlugins;

use ArrayObject;
use Countable;
use Gacela\Console\Application\DebugPlugins\MembershipReport;
use Gacela\Framework\Plugins\Membership\ListenerMember;
use Gacela\Framework\Plugins\Membership\Members;
use Gacela\Framework\Plugins\Membership\PluginMember;
use Gacela\Framework\Plugins\Membership\TagMember;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use stdClass;

final class MembershipReportTest extends TestCase
{
    public function test_a_declared_member_also_carrying_the_attribute_is_listed_once(): void
    {
        $report = MembershipReport::of(
            [Countable::class => [ArrayObject::class]],
            [],
            new Members([new PluginMember(Countable::class, ArrayObject::class, 0), new PluginMember(Countable::class, self::class, 5)]),
        );

        self::assertSame([
            ['contract' => Countable::class, 'member' => ArrayObject::class, 'source' => 'gacela.php'],
            ['contract' => Countable::class, 'member' => self::class, 'source' => '#[Plugin] priority 5'],
        ], $report['stacks']);
    }

    public function test_a_member_of_an_undeclared_stack_is_shown_as_never_read(): void
    {
        $report = MembershipReport::of([], [], new Members([new PluginMember(IteratorAggregate::class, ArrayObject::class, 0)]));

        self::assertSame([
            ['contract' => IteratorAggregate::class, 'member' => ArrayObject::class, 'source' => '#[Plugin], stack not declared: never read'],
        ], $report['stacks']);
    }

    public function test_each_tag_lists_its_declared_ids_then_its_attribute_ones(): void
    {
        $report = MembershipReport::of(
            [],
            ['exporters' => [ArrayObject::class], 'reports' => [stdClass::class]],
            new Members([], [
                new TagMember('exporters', ArrayObject::class),
                new TagMember('exporters', self::class),
                new TagMember('audits', self::class),
            ]),
        );

        self::assertSame([
            ['tag' => 'exporters', 'id' => ArrayObject::class, 'source' => 'gacela.php'],
            ['tag' => 'exporters', 'id' => self::class, 'source' => '#[Tag]'],
            ['tag' => 'reports', 'id' => stdClass::class, 'source' => 'gacela.php'],
            ['tag' => 'audits', 'id' => self::class, 'source' => '#[Tag]'],
        ], $report['tags']);
    }

    public function test_listeners_and_problems_are_reported(): void
    {
        $report = MembershipReport::of([], [], new Members([], [], [new ListenerMember(Countable::class, ArrayObject::class, 'count')], ['App\\Broken::on() has #[AsListener] and no event']));

        self::assertSame([['event' => Countable::class, 'listener' => 'ArrayObject::count()']], $report['listeners']);
        self::assertSame(['App\\Broken::on() has #[AsListener] and no event'], $report['problems']);
    }

    public function test_an_id_declared_twice_is_listed_once_as_it_is_held(): void
    {
        $report = MembershipReport::of(
            [Countable::class => [ArrayObject::class, ArrayObject::class]],
            ['exporters' => [stdClass::class, stdClass::class]],
            new Members(),
        );

        self::assertCount(1, $report['stacks']);
        self::assertCount(1, $report['tags']);
    }

    public function test_a_numeric_tag_name_stays_a_string(): void
    {
        $report = MembershipReport::of([], ['2024' => [stdClass::class]], new Members());

        self::assertSame('2024', $report['tags'][0]['tag']);
    }
}
