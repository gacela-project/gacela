<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaConfigBuilder;

use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
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
}
