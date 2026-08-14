<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use ArrayObject;
use Countable;
use Gacela\Console\Application\Doctor\Check\HandlerRegistryCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * `LazyHandlerRegistry` resolves a key through the container on *first lookup*,
 * so the keys a run does not take are the registrations nobody has checked --
 * which is most of them, most of the time.
 *
 * And it is weaker than the plugin stack beside it: a missing class raises a
 * `TypeError` on the consumer, and a class that does not implement the contract
 * is not refused at all.
 */
final class HandlerRegistryCheckTest extends TestCase
{
    public function test_a_handler_class_that_does_not_exist_is_reported_as_missing(): void
    {
        $result = (new HandlerRegistryCheck([Countable::class => ['a' => 'Acme\\NoSuchHandler']]))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(
            ['Acme\\NoSuchHandler — registered as "a" in the "Countable" registry, and no such class exists'],
            $result->details,
        );
    }

    /**
     * The one the registry itself never catches: it hands the object back, and
     * the failure surfaces as a call to a method it does not have.
     */
    public function test_a_handler_that_does_not_implement_the_contract_says_so(): void
    {
        $result = (new HandlerRegistryCheck([Countable::class => ['a' => stdClass::class]]))->run();

        self::assertSame(
            ['stdClass — registered as "a" in the "Countable" registry and does not implement it'],
            $result->details,
        );
    }

    public function test_a_handler_satisfying_its_registry_passes(): void
    {
        $result = (new HandlerRegistryCheck([Countable::class => ['a' => ArrayObject::class]]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 handler(s) satisfy their registry'], $result->details);
    }

    public function test_a_project_registering_nothing_is_not_warned_at(): void
    {
        $result = (new HandlerRegistryCheck([]))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no handler registries registered'], $result->details);
    }

    /**
     * A contract that does not exist makes every handler under it unsatisfiable,
     * and `is_a()` would answer false without saying why.
     */
    public function test_a_contract_that_does_not_exist_is_named_as_the_problem(): void
    {
        $result = (new HandlerRegistryCheck(['Acme\\NoSuchContract' => ['a' => ArrayObject::class]]))->run();

        self::assertSame(
            ['ArrayObject — the "Acme\\NoSuchContract" registry names a contract that does not exist'],
            $result->details,
        );
    }

    /**
     * The key, not just the class. A registry is a map: the same handler can be
     * registered under several keys, and the key is what a consumer asks for --
     * so a finding naming only the class does not say which lookup breaks.
     */
    public function test_the_key_is_named_so_the_finding_says_which_lookup_breaks(): void
    {
        $result = (new HandlerRegistryCheck([
            Countable::class => ['email' => stdClass::class, 'sms' => stdClass::class],
        ]))->run();

        self::assertSame(
            [
                'stdClass — registered as "email" in the "Countable" registry and does not implement it',
                'stdClass — registered as "sms" in the "Countable" registry and does not implement it',
            ],
            $result->details,
        );
    }

    /**
     * Every problem across every registry, not just the first: two broken
     * registrations should be one round trip, not two.
     */
    public function test_every_problem_in_every_registry_is_named(): void
    {
        $result = (new HandlerRegistryCheck([
            Countable::class => ['a' => 'Acme\\NoSuchHandler', 'b' => stdClass::class],
            'Acme\\Other' => ['c' => ArrayObject::class],
        ]))->run();

        self::assertCount(3, $result->details);
    }

    /**
     * A good handler beside a broken one still reports the broken one -- and
     * only it.
     */
    public function test_a_valid_handler_does_not_mask_a_broken_sibling(): void
    {
        $result = (new HandlerRegistryCheck([
            Countable::class => ['ok' => ArrayObject::class, 'bad' => stdClass::class],
        ]))->run();

        self::assertCount(1, $result->details);
        self::assertStringContainsString('"bad"', $result->details[0]);
    }

    /**
     * An integer key is a legitimate registry key and has to survive into the
     * message, which is built for strings.
     */
    public function test_an_integer_key_is_reported_too(): void
    {
        $result = (new HandlerRegistryCheck([Countable::class => [7 => stdClass::class]]))->run();

        self::assertStringContainsString('registered as "7"', $result->details[0]);
    }

    /**
     * The whole sentence: it explains *why* asking early is worth it, which is
     * the reason the check exists, and a substring assertion would pass for
     * half of it in the wrong order.
     */
    public function test_the_remediation_says_why_the_failure_would_surface_elsewhere(): void
    {
        $result = (new HandlerRegistryCheck([Countable::class => ['a' => 'Acme\\NoSuchHandler']]))->run();

        self::assertSame(
            'a registry resolves a key on first lookup, so this would otherwise surface '
            . 'wherever that key is first asked for rather than where it was registered',
            $result->remediation,
        );
    }
}
