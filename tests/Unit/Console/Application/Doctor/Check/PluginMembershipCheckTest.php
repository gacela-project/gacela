<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

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
        $result = (new PluginMembershipCheck([], [], cacheIsWarm: false, appEnv: 'prod'))->run();

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
            [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cacheIsWarm: true,
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
            [new PluginMember(Countable::class, stdClass::class, 0)],
            cacheIsWarm: true,
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
            [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cacheIsWarm: false,
            appEnv: 'prod',
        ))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertSame('run `bin/gacela cache:warm --attributes` when deploying', $result->remediation);
    }

    public function test_scanning_in_development_is_fine(): void
    {
        $result = (new PluginMembershipCheck(
            [Countable::class => []],
            [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cacheIsWarm: false,
            appEnv: 'dev',
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 #[Plugin] class(es) join declared stacks, found by scanning on first use'], $result->details);
    }

    public function test_an_unset_environment_is_not_taken_for_production(): void
    {
        $result = (new PluginMembershipCheck(
            [Countable::class => []],
            [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cacheIsWarm: false,
            appEnv: null,
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
    }

    public function test_a_warmed_cache_says_where_members_come_from(): void
    {
        $result = (new PluginMembershipCheck(
            [Countable::class => []],
            [new PluginMember(Countable::class, ArrayObject::class, 0)],
            cacheIsWarm: true,
            appEnv: 'prod',
        ))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame(['1 #[Plugin] class(es) join declared stacks, read from the warmed cache'], $result->details);
    }
}
