<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use ArrayObject;
use Countable;
use Gacela\Console\Application\Doctor\Check\PluginStackCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * `LazyPluginStack` already refuses both shapes, but on *first resolve* --
 * a class name in `gacela.php` is a string until something loads it. A stack
 * nothing has iterated is a registration nobody has checked, so a typo on a
 * rarely-taken path waits until production to be read out.
 */
final class PluginStackCheckTest extends TestCase
{
    public function test_a_plugin_class_that_does_not_exist_is_reported_as_missing(): void
    {
        $result = (new PluginStackCheck([Countable::class => ['Acme\\NoSuchPlugin']]))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(
            ['Acme\\NoSuchPlugin — registered in the "Countable" stack, and no such class exists'],
            $result->details,
        );
    }

    /**
     * The two are reported differently on purpose, in the order
     * `LazyPluginStack` asks them: a missing class read as one that "does not
     * implement" the contract sends the reader to inspect an `implements`
     * clause on a file that is not there.
     */
    public function test_a_class_that_does_not_implement_the_contract_says_so(): void
    {
        $result = (new PluginStackCheck([Countable::class => [stdClass::class]]))->run();

        self::assertSame(
            ['stdClass — registered in the "Countable" stack and does not implement it'],
            $result->details,
        );
    }

    public function test_a_plugin_satisfying_its_contract_passes(): void
    {
        $result = (new PluginStackCheck([Countable::class => [ArrayObject::class]]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 plugin(s) satisfy their stack'], $result->details);
    }

    public function test_a_project_registering_no_stack_is_not_warned_at(): void
    {
        $result = (new PluginStackCheck([]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no plugin stacks registered'], $result->details);
    }

    /**
     * A contract that does not exist makes every plugin under it unsatisfiable,
     * and `is_a()` would answer false without saying why.
     */
    public function test_a_contract_that_does_not_exist_is_named_as_the_problem(): void
    {
        $result = (new PluginStackCheck(['Acme\\NoSuchContract' => [ArrayObject::class]]))->run();

        self::assertSame(
            ['ArrayObject — the "Acme\\NoSuchContract" stack names a contract that does not exist'],
            $result->details,
        );
    }

    /**
     * Every problem across every stack, not just the first: two broken
     * registrations should be one round trip, not two.
     */
    public function test_every_problem_in_every_stack_is_named(): void
    {
        $result = (new PluginStackCheck([
            Countable::class => ['Acme\\NoSuchPlugin', stdClass::class],
            'Acme\\Other' => [ArrayObject::class],
        ]))->run();

        self::assertCount(3, $result->details);
    }

    /**
     * A stack with a good plugin beside a broken one still reports the broken
     * one -- and only it.
     */
    public function test_a_valid_plugin_does_not_mask_a_broken_sibling(): void
    {
        $result = (new PluginStackCheck([Countable::class => [ArrayObject::class, stdClass::class]]))->run();

        self::assertCount(1, $result->details);
        self::assertStringContainsString('stdClass', $result->details[0]);
    }

    /**
     * The whole sentence: it explains *why* asking early is worth it, which is
     * the reason the check exists, and a substring assertion would pass for
     * half of it in the wrong order.
     */
    public function test_the_remediation_says_why_the_failure_would_surface_elsewhere(): void
    {
        $result = (new PluginStackCheck([Countable::class => ['Acme\\NoSuchPlugin']]))->run();

        self::assertSame(
            'a plugin stack resolves on first use, so this would otherwise surface '
            . 'wherever the stack is first iterated rather than where it was registered',
            $result->remediation,
        );
    }
}
