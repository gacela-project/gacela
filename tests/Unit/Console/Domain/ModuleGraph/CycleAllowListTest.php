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

    /**
     * The sibling flag on the same command, `--rules`, takes an object with a
     * `rules` key, so reaching for `{"cycles": [...]}` here is the natural
     * mistake -- I made it with the page open. The values of that object were
     * walked as if they were entries, and the report blamed "entry #0" for a
     * file whose shape was wrong.
     */
    public function test_an_object_instead_of_a_list_of_entries_is_rejected(): void
    {
        $this->expectException(MalformedCycleAllowListException::class);
        // The `--rules` hint, not the "wrap it" one: nothing here looks like a
        // stray entry, so the likely mistake is the sibling flag's shape.
        $this->expectExceptionMessage('Note that --rules takes an object, and this one takes the array directly.');

        CycleAllowList::fromDecodedJson(['cycles' => [['A', 'B']]]);
    }

    public function test_the_shape_error_names_the_keys_it_found(): void
    {
        $this->expectException(MalformedCycleAllowListException::class);
        $this->expectExceptionMessage('found an object with keys: cycles, extra');

        CycleAllowList::fromDecodedJson(['cycles' => [], 'extra' => 1]);
    }

    /**
     * The other likely slip: a correct entry that was never wrapped in the
     * array. Worth its own sentence, because the fix is a pair of brackets.
     */
    public function test_a_single_unwrapped_entry_says_to_wrap_it(): void
    {
        $this->expectException(MalformedCycleAllowListException::class);
        $this->expectExceptionMessage('wrap it in [ ]');

        CycleAllowList::fromDecodedJson(['modules' => ['A', 'B'], 'reason' => 'reviewed']);
    }

    /**
     * Either key is enough to recognise a stray entry: a file holding only
     * `{"modules": [...]}` forgot both the reason and the brackets, and the
     * brackets are the part that is not obvious.
     */
    public function test_one_entry_key_is_enough_to_suggest_wrapping(): void
    {
        $this->expectException(MalformedCycleAllowListException::class);
        $this->expectExceptionMessage('wrap it in [ ]');

        CycleAllowList::fromDecodedJson(['modules' => ['A', 'B']]);
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
