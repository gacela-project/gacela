<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ResolvableType;
use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\Framework\Exception\ResolvableTypeException;
use PHPUnit\Framework\TestCase;

final class ResolvableTypesTest extends TestCase
{
    protected function tearDown(): void
    {
        ResolvableTypes::resetToBuiltIn();
    }

    public function test_without_a_declaration_only_the_pillars_exist(): void
    {
        self::assertSame(['Facade', 'Factory', 'Config', 'Provider'], ResolvableTypes::kinds());
        self::assertSame([], ResolvableTypes::declaredKinds());
    }

    public function test_a_declared_kind_joins_the_pillars(): void
    {
        ResolvableTypes::syncFrom([...ResolvableTypes::BUILT_IN, 'Exporter' => ['Exporter', 'Feed']]);

        self::assertSame(['Exporter'], ResolvableTypes::declaredKinds());
        self::assertSame(['Exporter', 'Feed'], ResolvableTypes::suffixesOf('Exporter'));
    }

    public function test_every_declared_kind_is_reported_not_just_the_first(): void
    {
        ResolvableTypes::syncFrom([...ResolvableTypes::BUILT_IN, 'Exporter' => ['Exporter'], 'Reader' => ['Reader']]);

        self::assertSame(['Exporter', 'Reader'], ResolvableTypes::declaredKinds());
    }

    public function test_an_unknown_kind_answers_to_its_own_name(): void
    {
        self::assertSame(['Nothing'], ResolvableTypes::suffixesOf('Nothing'));
    }

    public function test_syncing_nothing_leaves_the_pillars_in_place(): void
    {
        ResolvableTypes::syncFrom([]);

        self::assertSame(ResolvableTypes::BUILT_IN, ResolvableTypes::all());
    }

    /**
     * The ordering is the whole point of the match order: a declared
     * `ServiceProvider` ends with the built-in `Provider`, and the longer
     * suffix has to win or the project's kind is unreachable.
     */
    public function test_a_longer_suffix_wins_over_the_one_it_ends_with(): void
    {
        ResolvableTypes::syncFrom([...ResolvableTypes::BUILT_IN, 'Registrar' => ['ServiceProvider']]);

        self::assertSame('Registrar', ResolvableType::fromClassName('App\Foo\FooServiceProvider')->resolvableType());
        self::assertSame('Provider', ResolvableType::fromClassName('App\Foo\FooProvider')->resolvableType());
    }

    public function test_the_match_order_runs_longest_suffix_first(): void
    {
        ResolvableTypes::syncFrom(['A' => ['Zz'], 'B' => ['YyyyY'], 'C' => ['Xxx']]);

        self::assertSame(
            ['YyyyY', 'Xxx', 'Zz'],
            array_column(ResolvableTypes::matchOrder(), 'suffix'),
        );
    }

    /**
     * A suffix two kinds share cannot say which kind a name belongs to, so it
     * answers for neither and the caller falls back to the last segment.
     */
    public function test_a_suffix_two_kinds_share_names_neither(): void
    {
        ResolvableTypes::syncFrom(['A' => ['Shared'], 'B' => ['Shared', 'Own']]);

        self::assertSame(['Own'], array_column(ResolvableTypes::matchOrder(), 'suffix'));
        self::assertSame('FooShared', ResolvableType::fromClassName('App\Foo\FooShared')->resolvableType());
    }

    public function test_the_match_order_is_memoized_until_the_declarations_move(): void
    {
        ResolvableTypes::syncFrom([...ResolvableTypes::BUILT_IN, 'Exporter' => ['Exporter']]);
        $first = ResolvableTypes::matchOrder();

        self::assertSame($first, ResolvableTypes::matchOrder());

        ResolvableTypes::syncFrom(ResolvableTypes::BUILT_IN);

        self::assertNotSame($first, ResolvableTypes::matchOrder());
    }

    /**
     * The caller clears the key memos on the strength of this answer, so a
     * sync that changed the set has to say so.
     */
    public function test_a_sync_that_changes_the_set_reports_it(): void
    {
        self::assertTrue(ResolvableTypes::syncFrom([...ResolvableTypes::BUILT_IN, 'Exporter' => ['Exporter']]));
    }

    /**
     * And one that changed nothing must not: every memo would be dropped for
     * nothing, on every bootstrap of every project that declares no kind.
     */
    public function test_a_sync_that_changes_nothing_reports_that_too(): void
    {
        ResolvableTypes::syncFrom([...ResolvableTypes::BUILT_IN, 'Exporter' => ['Exporter']]);

        self::assertFalse(ResolvableTypes::syncFrom([...ResolvableTypes::BUILT_IN, 'Exporter' => ['Exporter']]));
    }

    public function test_returning_to_the_pillars_reports_whether_it_changed(): void
    {
        ResolvableTypes::syncFrom([...ResolvableTypes::BUILT_IN, 'Exporter' => ['Exporter']]);

        self::assertTrue(ResolvableTypes::resetToBuiltIn());
        self::assertFalse(ResolvableTypes::resetToBuiltIn());
    }

    public function test_a_suffix_two_declared_kinds_share_is_refused(): void
    {
        $this->expectException(ResolvableTypeException::class);
        $this->expectExceptionMessage('already belongs to the "Exporter" kind');

        ResolvableTypes::assertUnambiguous([
            ...ResolvableTypes::BUILT_IN,
            'Exporter' => ['Feed'],
            'Importer' => ['Feed'],
        ]);
    }

    /**
     * The pillars may share one, and did before any of this existed.
     */
    public function test_a_suffix_two_pillars_share_is_allowed(): void
    {
        ResolvableTypes::assertUnambiguous([
            'Facade' => ['Facade', 'Extra'],
            'Factory' => ['Factory', 'Extra'],
        ]);

        self::assertTrue(true);
    }
}
