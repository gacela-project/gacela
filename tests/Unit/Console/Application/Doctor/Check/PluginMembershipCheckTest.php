<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use ArgumentCountError;
use ArrayObject;
use Countable;
use Gacela\Console\Application\Doctor\Check\PluginMembershipCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Framework\Plugins\Membership\PluginMember;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PluginMembershipCheckTest extends TestCase
{
    public function test_no_attribute_members_is_ok(): void
    {
        $result = (new PluginMembershipCheck([], static fn (): array => [], cached: null, appEnv: 'prod'))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['no #[Plugin] classes'], $result->details);
    }

    /**
     * Without the declaration the member is never read: the stack does not
     * exist, so asking for it fails, and the attribute looks like it works
     * until then.
     */
    public function test_a_member_of_an_undeclared_stack_is_an_error(): void
    {
        $result = (new PluginMembershipCheck(
            [],
            static fn (): array => [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cached: [new PluginMember(Countable::class, ArrayObject::class, 0)],
            appEnv: null,
        ))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(
            ['ArrayObject — #[Plugin] joins the "Countable" stack, which gacela.php does not declare'],
            $result->details,
        );
    }

    public function test_a_member_that_does_not_implement_its_contract_is_an_error(): void
    {
        $result = (new PluginMembershipCheck(
            [Countable::class => []],
            static fn (): array => [new PluginMember(Countable::class, stdClass::class, 0)],
            cached: [new PluginMember(Countable::class, stdClass::class, 0)],
            appEnv: null,
        ))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(
            ['stdClass — #[Plugin] joins the "Countable" stack and does not implement it'],
            $result->details,
        );
    }

    public function test_scanning_in_production_is_a_warning(): void
    {
        $result = (new PluginMembershipCheck(
            [Countable::class => []],
            static fn (): array => [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cached: null,
            appEnv: 'prod',
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame(['1 #[Plugin] class(es) are found by scanning the module paths on the first use of a stack'], $result->details);
        self::assertSame('run `bin/gacela cache:warm --attributes` when deploying', $result->remediation);
    }

    public function test_scanning_in_development_is_fine(): void
    {
        $result = (new PluginMembershipCheck(
            [Countable::class => []],
            static fn (): array => [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cached: null,
            appEnv: 'dev',
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 #[Plugin] class(es) join declared stacks, found by scanning on first use'], $result->details);
    }

    public function test_an_unset_environment_is_not_taken_for_production(): void
    {
        $result = (new PluginMembershipCheck(
            [Countable::class => []],
            static fn (): array => [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cached: null,
            appEnv: null,
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }

    public function test_a_warmed_cache_says_where_members_come_from(): void
    {
        $result = (new PluginMembershipCheck(
            [Countable::class => []],
            static fn (): array => [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cached: [new PluginMember(Countable::class, ArrayObject::class, 0)],
            appEnv: 'prod',
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 #[Plugin] class(es) join declared stacks, read from the warmed cache'], $result->details);
    }

    /**
     * `#[Plugin]` with no contract throws from `newInstance()`, and a class whose
     * parent is gone throws from `class_exists()`: the misconfiguration this
     * check is for, so it is reported here instead of ending the run.
     */
    public function test_a_scan_that_throws_is_reported(): void
    {
        $result = (new PluginMembershipCheck(
            [],
            static fn (): array => throw new ArgumentCountError('Too few arguments to Plugin::__construct()'),
            cached: null,
            appEnv: null,
        ))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(['the #[Plugin] scan failed: Too few arguments to Plugin::__construct()'], $result->details);
    }

    /**
     * The application reads the cache, not the code: a class removed since
     * the warm is still listed, and the stack fails on its first use.
     */
    public function test_a_cached_class_that_no_longer_exists_is_an_error(): void
    {
        $result = (new PluginMembershipCheck(
            [Countable::class => []],
            static fn (): array => [],
            cached: [new PluginMember(Countable::class, 'App\\RemovedSinceTheWarm', 0)],
            appEnv: null,
        ))->run();

        self::assertSame(CheckStatus::Error, $result->status);
        self::assertSame(['App\\RemovedSinceTheWarm — listed in the #[Plugin] cache, and no such class exists'], $result->details);
    }

    public function test_a_cache_that_differs_from_the_code_is_a_warning(): void
    {
        $result = (new PluginMembershipCheck(
            [Countable::class => []],
            static fn (): array => [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cached: [],
            appEnv: null,
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame('run `bin/gacela cache:warm --attributes`, or `cache:clear` to scan again', $result->remediation);
    }
}
