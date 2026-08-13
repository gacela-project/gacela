<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaConfigBuilder;

use Countable;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Exception\ResolvableTypeException;
use PHPUnit\Framework\TestCase;

final class SuffixTypesBuilderTest extends TestCase
{
    public function test_build_returns_defaults_when_nothing_added(): void
    {
        $builder = new SuffixTypesBuilder();

        self::assertSame(
            [
                'Facade' => ['Facade'],
                'Factory' => ['Factory'],
                'Config' => ['Config'],
                'Provider' => ['Provider'],
            ],
            $builder->build(),
        );
    }

    public function test_build_deduplicates_repeated_suffixes_per_bucket(): void
    {
        $builder = (new SuffixTypesBuilder())
            ->addFacade('Facade')
            ->addFacade('FacadeX')
            ->addFacade('FacadeX')
            ->addFactory('Factory')
            ->addFactory('FactoryY')
            ->addFactory('FactoryY')
            ->addConfig('Config')
            ->addConfig('ConfigZ')
            ->addConfig('ConfigZ')
            ->addProvider('Provider')
            ->addProvider('ProviderW')
            ->addProvider('ProviderW');

        $built = $builder->build();

        self::assertSame(['Facade', 'FacadeX'], $built['Facade']);
        self::assertSame(['Factory', 'FactoryY'], $built['Factory']);
        self::assertSame(['Config', 'ConfigZ'], $built['Config']);
        self::assertSame(['Provider', 'ProviderW'], $built['Provider']);
    }

    public function test_build_result_is_a_list_after_dedup_even_with_gaps(): void
    {
        // array_unique preserves original keys, leaving index gaps
        // (e.g. [0 => 'A', 2 => 'B']); array_values must reindex so the
        // returned list has sequential integer keys.
        $builder = (new SuffixTypesBuilder())
            ->addFacade('Facade')
            ->addFacade('Facade')
            ->addFacade('Extra')
            ->addFactory('Factory')
            ->addFactory('Factory')
            ->addFactory('Extra')
            ->addConfig('Config')
            ->addConfig('Config')
            ->addConfig('Extra')
            ->addProvider('Provider')
            ->addProvider('Provider')
            ->addProvider('Extra');

        $built = $builder->build();

        self::assertSame([0, 1], array_keys($built['Facade']));
        self::assertSame([0, 1], array_keys($built['Factory']));
        self::assertSame([0, 1], array_keys($built['Config']));
        self::assertSame([0, 1], array_keys($built['Provider']));
    }

    public function test_a_declared_kind_becomes_an_ordinary_key(): void
    {
        $built = (new SuffixTypesBuilder())
            ->declareType('Exporter', null, ['Exporter', 'Feed'])
            ->build();

        self::assertSame(['Exporter', 'Feed'], $built['Exporter']);
    }

    public function test_add_type_widens_any_kind_by_name(): void
    {
        $built = (new SuffixTypesBuilder())
            ->addType('Facade', 'Entrypoint')
            ->addType('Exporter', 'Feed')
            ->build();

        self::assertSame(['Facade', 'Entrypoint'], $built['Facade']);
        self::assertSame(['Feed'], $built['Exporter']);
    }

    public function test_a_declared_kind_answers_to_its_own_name_by_default(): void
    {
        $built = (new SuffixTypesBuilder())
            ->declareType('Exporter')
            ->build();

        self::assertSame(['Exporter'], $built['Exporter']);
    }

    /**
     * array_unique keeps the original keys, so a duplicate inside one call
     * leaves a gap. The stored value is handed straight to the resolver as a
     * list, so the reindex is the thing that keeps that promise true.
     */
    public function test_a_repeated_suffix_in_one_declaration_still_yields_a_list(): void
    {
        $built = (new SuffixTypesBuilder())
            ->declareType('Exporter', null, ['Feed', 'Feed', 'Sheet'])
            ->build();

        self::assertSame(['Feed', 'Sheet'], $built['Exporter']);
        self::assertSame([0, 1], array_keys($built['Exporter']));
    }

    public function test_declaring_a_kind_twice_widens_it(): void
    {
        $built = (new SuffixTypesBuilder())
            ->declareType('Exporter', null, ['Exporter'])
            ->declareType('Exporter', null, ['Feed', 'Exporter'])
            ->build();

        self::assertSame(['Exporter', 'Feed'], $built['Exporter']);
    }

    public function test_a_kind_must_have_a_name(): void
    {
        $this->expectException(ResolvableTypeException::class);
        $this->expectExceptionMessage('non-empty kind');

        (new SuffixTypesBuilder())->declareType('');
    }

    public function test_a_kind_whose_base_does_not_exist_is_refused(): void
    {
        $this->expectException(ResolvableTypeException::class);
        $this->expectExceptionMessage('Never\Declared\Klass');

        /** @psalm-suppress ArgumentTypeCoercion */
        (new SuffixTypesBuilder())->declareType('Exporter', 'Never\Declared\Klass');
    }

    /**
     * A name resolving to nothing is usually a namespace typo or an autoloader
     * that has not seen the file, so the refusal carries the tips for that --
     * which this message did not, while every other "does not exist" in the
     * framework did.
     */
    public function test_a_missing_base_is_refused_with_the_tips_for_a_missing_class(): void
    {
        try {
            /** @psalm-suppress ArgumentTypeCoercion */
            (new SuffixTypesBuilder())->declareType('Exporter', 'Never\\Declared\\Klass');
            self::fail('Expected ResolvableTypeException');
        } catch (ResolvableTypeException $resolvableTypeException) {
            $message = $resolvableTypeException->getMessage();

            self::assertStringContainsString("Run 'composer dump-autoload' to refresh autoloader", $message);
            // What went wrong first, what to try about it after.
            self::assertStringStartsWith('The "Exporter" kind names', $message);
        }
    }

    public function test_an_interface_is_a_valid_base(): void
    {
        $built = (new SuffixTypesBuilder())
            ->declareType('Exporter', Countable::class)
            ->build();

        self::assertSame(['Exporter'], $built['Exporter']);
    }

    /**
     * A declared kind claiming a suffix another kind owns would resolve by
     * declaration order, which is not something a project can reason about.
     */
    public function test_a_declared_kind_cannot_claim_a_pillars_suffix(): void
    {
        $this->expectException(ResolvableTypeException::class);
        // The refusal and the way out of it, in one message.
        $this->expectExceptionMessageMatches('/already belongs to the "Facade" kind.*Give "Exporter" a suffix of its own/s');

        (new SuffixTypesBuilder())->declareType('Exporter', null, ['Facade']);
    }

    public function test_a_pillar_cannot_claim_a_declared_kinds_suffix_either(): void
    {
        $this->expectException(ResolvableTypeException::class);
        $this->expectExceptionMessage('already belongs to the "Exporter" kind');

        (new SuffixTypesBuilder())
            ->declareType('Exporter', null, ['Feed'])
            ->addFacade('Feed');
    }
}
