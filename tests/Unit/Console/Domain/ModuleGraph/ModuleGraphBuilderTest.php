<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph;

use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Console\Domain\ModuleGraph\ModuleGraphBuilder;
use PHPUnit\Framework\TestCase;

final class ModuleGraphBuilderTest extends TestCase
{
    private const NS = 'GacelaTest\Unit\Console\Domain\ModuleGraph\Fixture';

    public function test_records_every_module_it_is_given(): void
    {
        $graph = (new ModuleGraphBuilder())->build([
            $this->module('Alpha'),
            $this->module('Zebra'),
        ]);

        self::assertSame([self::NS . '\Alpha' => [], self::NS . '\Zebra' => []], $graph);
    }

    public function test_reports_every_dependency_of_a_module_sorted(): void
    {
        $graph = (new ModuleGraphBuilder())->build([
            $this->module('Hub'),
            $this->module('AlphaExtra'),
            $this->module('Zebra'),
        ]);

        self::assertSame(
            [self::NS . '\AlphaExtra', self::NS . '\Zebra'],
            $graph[self::NS . '\Hub'],
            'Hub imports Zebra first, so an unsorted list would come out the other way round',
        );
    }

    /**
     * `App\Alpha` is a prefix of `App\AlphaExtra` as a plain string, but not as
     * a namespace. Matching without the separator invents a dependency on a
     * module the code never mentions.
     */
    public function test_a_module_whose_name_prefixes_another_is_not_a_dependency(): void
    {
        $graph = (new ModuleGraphBuilder())->build([
            $this->module('Hub'),
            $this->module('Alpha'),
            $this->module('AlphaExtra'),
        ]);

        self::assertSame([self::NS . '\AlphaExtra'], $graph[self::NS . '\Hub']);
    }

    /**
     * Hub's directory holds a .txt file containing a `use` line. Only PHP files
     * declare dependencies.
     */
    public function test_non_php_files_in_a_module_directory_are_not_scanned(): void
    {
        $graph = (new ModuleGraphBuilder())->build([
            $this->module('Hub'),
            $this->module('Alpha'),
        ]);

        self::assertSame([], $graph[self::NS . '\Hub'], 'the .txt file must not contribute a dependency');
    }

    public function test_a_module_does_not_depend_on_itself(): void
    {
        $graph = (new ModuleGraphBuilder())->build([$this->module('Hub')]);

        self::assertSame([], $graph[self::NS . '\Hub']);
    }

    public function test_no_modules_produce_an_empty_graph(): void
    {
        self::assertSame([], (new ModuleGraphBuilder())->build([]));
    }

    /**
     * The headline case: a grouped import spanning two modules, split across
     * lines and aliased. The previous regex stopped at the brace and returned
     * the bare prefix, so neither edge existed.
     *
     * The same file imports a *function* from AlphaExtra, which must not
     * become an edge -- that is what keeps the fix from over-reporting.
     */
    public function test_a_grouped_import_produces_an_edge_per_module(): void
    {
        $graph = (new ModuleGraphBuilder())->build([
            $this->module('Grouped'),
            $this->module('Alpha'),
            $this->module('AlphaExtra'),
            $this->module('Zebra'),
        ]);

        self::assertSame(
            [self::NS . '\Alpha', self::NS . '\Zebra'],
            $graph[self::NS . '\Grouped'],
        );
    }

    /**
     * The graph says an edge exists; this says where it was written. Both read
     * the same imports through the same parser, so a reported dependency always
     * has evidence and the evidence always names a reported dependency.
     */
    public function test_the_imports_behind_an_edge_name_the_file_and_the_line(): void
    {
        $evidence = (new ModuleGraphBuilder())
            ->importsPointingInto($this->module('Hub'), self::NS . '\Zebra');

        self::assertSame(
            [[
                'file' => $this->fixtureFile('Hub'),
                'line' => 9,
                'import' => self::NS . '\Zebra\Facade',
            ]],
            $evidence,
        );
    }

    /**
     * The whole statement is the evidence for every module it reaches, so both
     * entries of a group point at the `use` rather than at their own entry.
     */
    public function test_a_grouped_import_is_evidence_for_each_module_it_reaches(): void
    {
        $builder = new ModuleGraphBuilder();
        $groupedFile = $this->fixtureFile('Grouped');

        self::assertSame(
            [['file' => $groupedFile, 'line' => 8, 'import' => self::NS . '\Alpha\Facade']],
            $builder->importsPointingInto($this->module('Grouped'), self::NS . '\Alpha'),
        );

        self::assertSame(
            [['file' => $groupedFile, 'line' => 8, 'import' => self::NS . '\Zebra\Facade']],
            $builder->importsPointingInto($this->module('Grouped'), self::NS . '\Zebra'),
        );
    }

    /**
     * `Fixture\Alpha` is a plain-string prefix of the `Fixture\AlphaExtra`
     * import Hub declares. Evidence for a dependency the graph does not report
     * would send a reader to a line that is not the problem.
     */
    public function test_a_module_whose_name_prefixes_an_import_has_no_evidence(): void
    {
        self::assertSame(
            [],
            (new ModuleGraphBuilder())->importsPointingInto($this->module('Hub'), self::NS . '\Alpha'),
        );
    }

    public function test_a_module_with_no_import_into_the_dependency_has_no_evidence(): void
    {
        self::assertSame(
            [],
            (new ModuleGraphBuilder())->importsPointingInto($this->module('Alpha'), self::NS . '\Zebra'),
        );
    }

    private function fixtureFile(string $module): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'Fixture' . DIRECTORY_SEPARATOR
            . $module . DIRECTORY_SEPARATOR . 'Facade.php';
    }

    private function module(string $name): AppModule
    {
        /** @var class-string $facade */
        $facade = self::NS . '\\' . $name . '\Facade';

        return new AppModule(self::NS . '\\' . $name, $name, $facade);
    }
}
