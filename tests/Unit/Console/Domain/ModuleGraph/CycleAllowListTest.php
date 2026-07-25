<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph;

use Gacela\Console\Domain\ModuleGraph\CycleAllowList;
use Gacela\Console\Domain\ModuleGraph\MalformedCycleAllowListException;
use PHPUnit\Framework\TestCase;

final class CycleAllowListTest extends TestCase
{
    public function test_an_empty_allow_list_declares_every_cycle_undeclared(): void
    {
        $result = CycleAllowList::empty()->check([['A', 'B']]);

        self::assertFalse($result->isClean());
        self::assertSame([['A', 'B']], $result->undeclaredCycles);
        self::assertSame([], $result->staleAllowances);
    }

    public function test_no_cycles_and_no_allowances_is_clean(): void
    {
        self::assertTrue(CycleAllowList::empty()->check([])->isClean());
    }

    public function test_an_allowed_cycle_is_not_reported(): void
    {
        $result = $this->allowList([['modules' => ['A', 'B'], 'reason' => 'reviewed']])
            ->check([['A', 'B']]);

        self::assertTrue($result->isClean());
    }

    /**
     * The self-invalidating half: an allowance that outlives its cycle looks
     * like the check is still watching something.
     */
    public function test_an_allowance_whose_cycle_is_gone_is_reported_as_stale(): void
    {
        $result = $this->allowList([['modules' => ['A', 'B'], 'reason' => 'reviewed']])
            ->check([]);

        self::assertFalse($result->isClean());
        self::assertSame([['A', 'B']], $result->staleAllowances);
        self::assertSame([], $result->undeclaredCycles);
    }

    public function test_allowing_one_cycle_does_not_allow_another(): void
    {
        $result = $this->allowList([['modules' => ['A', 'B'], 'reason' => 'reviewed']])
            ->check([['A', 'B'], ['C', 'D']]);

        self::assertSame([['C', 'D']], $result->undeclaredCycles);
        self::assertSame([], $result->staleAllowances);
    }

    public function test_a_partially_overlapping_cycle_is_not_allowed(): void
    {
        $result = $this->allowList([['modules' => ['A', 'B'], 'reason' => 'reviewed']])
            ->check([['A', 'B', 'C']]);

        self::assertSame([['A', 'B', 'C']], $result->undeclaredCycles);
        self::assertSame([['A', 'B']], $result->staleAllowances);
    }

    public function test_module_order_in_the_file_does_not_matter(): void
    {
        $result = $this->allowList([['modules' => ['B', 'A'], 'reason' => 'reviewed']])
            ->check([['A', 'B']]);

        self::assertTrue($result->isClean());
    }

    public function test_exposes_the_reason_a_cycle_was_accepted(): void
    {
        $allowList = $this->allowList([['modules' => ['A', 'B'], 'reason' => 'bidirectional by design']]);

        self::assertSame('bidirectional by design', $allowList->reasonFor(['A', 'B']));
        self::assertNull($allowList->reasonFor(['C', 'D']));
    }

    public function test_an_entry_that_is_not_an_object_is_rejected(): void
    {
        $this->expectException(MalformedCycleAllowListException::class);
        $this->expectExceptionMessage('Allowed-cycle entry #0 must be an object with "modules" and "reason".');

        CycleAllowList::fromDecodedJson(['just a string']);
    }

    public function test_an_entry_with_fewer_than_two_modules_is_rejected(): void
    {
        $this->expectException(MalformedCycleAllowListException::class);
        $this->expectExceptionMessage('Allowed-cycle entry #0 must list at least two "modules".');

        CycleAllowList::fromDecodedJson([['modules' => ['A'], 'reason' => 'reviewed']]);
    }

    public function test_an_entry_without_a_reason_is_rejected(): void
    {
        $this->expectException(MalformedCycleAllowListException::class);
        $this->expectExceptionMessage(
            'Allowed-cycle entry #0 needs a non-empty "reason": an allowance nobody justified is indistinguishable from a cycle nobody noticed.',
        );

        CycleAllowList::fromDecodedJson([['modules' => ['A', 'B'], 'reason' => '']]);
    }

    public function test_the_reported_position_identifies_which_entry_is_wrong(): void
    {
        $this->expectException(MalformedCycleAllowListException::class);
        $this->expectExceptionMessage('Allowed-cycle entry #2 must list at least two "modules".');

        CycleAllowList::fromDecodedJson([
            ['modules' => ['A', 'B'], 'reason' => 'fine'],
            ['modules' => ['C', 'D'], 'reason' => 'also fine'],
            ['modules' => ['E'], 'reason' => 'broken'],
        ]);
    }

    public function test_a_non_string_module_does_not_count_towards_the_minimum(): void
    {
        $this->expectException(MalformedCycleAllowListException::class);
        $this->expectExceptionMessage('must list at least two "modules"');

        CycleAllowList::fromDecodedJson([['modules' => ['A', 42], 'reason' => 'reviewed']]);
    }

    public function test_an_empty_file_allows_nothing_and_is_stale_about_nothing(): void
    {
        self::assertTrue(CycleAllowList::fromDecodedJson([])->check([])->isClean());
    }

    /**
     * @param list<array{modules: list<string>, reason: string}> $entries
     */
    private function allowList(array $entries): CycleAllowList
    {
        return CycleAllowList::fromDecodedJson($entries);
    }
}
