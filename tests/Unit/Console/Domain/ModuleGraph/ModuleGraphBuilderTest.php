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

    private function module(string $name): AppModule
    {
        /** @var class-string $facade */
        $facade = self::NS . '\\' . $name . '\Facade';

        return new AppModule(self::NS . '\\' . $name, $name, $facade);
    }
}
