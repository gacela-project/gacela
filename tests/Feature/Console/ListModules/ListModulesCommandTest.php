<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ListModules;

use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

final class ListModulesCommandTest extends TestCase
{
    private CommandTester $command;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->command = new CommandTester(new ListModulesCommand());
    }

    public function test_list_modules_simple(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();
        $namespace = 'GacelaTest\\Feature\\Console\\ListModules';

        // Every discovered module is listed by namespace.
        self::assertStringContainsString($namespace . '\\LevelUp\\TestModule3', $output);
        self::assertStringContainsString($namespace . '\\TestModule1', $output);
        self::assertStringContainsString($namespace . '\\TestModule2', $output);

        // Each row marks the pillars that module actually has: TestModule3 has a
        // Facade, Factory and Config but no Provider; TestModule2 has only a Facade.
        self::assertSame(3, substr_count($this->rowFor($output, 'LevelUp\\TestModule3'), 'x'));
        self::assertSame(1, substr_count($this->rowFor($output, '\\TestModule2'), 'x'));
    }

    public function test_list_modules_detailed(): void
    {
        $this->command->execute(['--detailed' => true]);

        $output = $this->command->getDisplay();
        $namespace = 'GacelaTest\\Feature\\Console\\ListModules';

        // Modules are numbered from 1, in discovery order.
        self::assertStringContainsString('1.- TestModule3', $output);
        self::assertStringContainsString('2.- TestModule1', $output);
        self::assertStringContainsString('3.- TestModule2', $output);
        self::assertStringNotContainsString('0.-', $output);

        // A pillar the module has reports its fully-qualified class name.
        self::assertStringContainsString('Facade: ' . $namespace . '\\TestModule1\\TestModule1Facade', $output);
        self::assertStringContainsString('Provider: ' . $namespace . '\\TestModule1\\TestModule1Provider', $output);

        // A pillar it does not have renders blank instead of a class name:
        // TestModule1 has no Config, and TestModule2 has only a Facade.
        self::assertMatchesRegularExpression('/^Config:\s*$/m', $output);
        self::assertStringNotContainsString('TestModule2Factory', $output);
    }

    public function test_list_modules_not_detailed(): void
    {
        $this->command->execute(['--detailed' => false]);

        $output = $this->command->getDisplay();

        // Verify this is the simple table view (not detailed view)
        self::assertStringContainsString('┌────', $output, 'Should contain table borders');
        self::assertStringNotContainsString('============================', $output, 'Should not contain detailed view separators');
        self::assertStringContainsString('TestModule3', $output);
    }

    #[DataProvider('commandInputProvider')]
    public function test_list_modules_with_filter(string $input): void
    {
        $this->command->execute(['filter' => $input]);

        $out = $this->command->getDisplay();

        self::assertStringContainsString('TestModule1', $out);
        self::assertStringNotContainsString('TestModule2', $out);
        self::assertStringNotContainsString('TestModule3', $out);
        self::assertStringNotContainsString('vendor', $out);
        self::assertStringNotContainsString('ToBeIgnored', $out);
    }

    /**
     * A filter that matched nothing and a project where nothing is a module are
     * different answers. With no argument the command used to give the second
     * in the words of the first -- `No modules match filter ""`, quoting an
     * empty filter as though the reader had typed one.
     *
     * The hint names the cause worth naming: discovery reflects on the class to
     * see whether it descends from `AbstractFacade`, so a Facade whose
     * namespace composer cannot map is skipped in silence, and the files sitting
     * on disk make the empty list read as a bug in the command. It cost me an
     * investigation before it cost anyone else one.
     */
    public function test_finding_nothing_without_a_filter_does_not_quote_an_empty_one(): void
    {
        // A real directory with nothing in it: pointing at one that does not
        // exist makes Gacela warn about the path instead, which is a different
        // report and would leave this asserting on the wrong thing.
        $emptyDir = sys_get_temp_dir() . '/gacela-empty-' . bin2hex(random_bytes(4));
        mkdir($emptyDir, 0777, true);

        try {
            Gacela::bootstrap($emptyDir, static function (GacelaConfig $config): void {
                $config->resetInMemoryCache();
                $config->setAppModulePaths(['.']);
            });

            $tester = new CommandTester(new ListModulesCommand());
            $tester->execute([]);
            $display = $tester->getDisplay();

            self::assertStringContainsString('No modules found.', $display);
            self::assertStringNotContainsString('filter ""', $display);
            self::assertStringContainsString('autoloadable', $display);

            // Discovery accepts by inheritance and never reads a suffix, so the
            // hint must not send a reader off to rename files. Pinned as words
            // because that is the whole of what this sentence is.
            self::assertStringContainsString('extending AbstractFacade', $display);
            self::assertStringNotContainsString('the filename carries the suffix', $display);
        } finally {
            // Names exactly what this test created.
            rmdir($emptyDir);
        }
    }

    /**
     * The empty report says what to check but never said where it looked.
     *
     * `appModulePaths` narrows discovery to a subset of the project -- this
     * repository's own `gacela.php` pins it to `src` -- and a reader whose
     * module sits outside that subset sees files on disk, an empty list, and
     * no way to connect the two without opening the config. Naming the paths
     * turns "why is my module missing" into one line of output.
     */
    public function test_finding_nothing_names_the_paths_that_were_scanned(): void
    {
        $emptyDir = sys_get_temp_dir() . '/gacela-scanned-' . bin2hex(random_bytes(4));
        mkdir($emptyDir . '/inner', 0777, true);

        try {
            Gacela::bootstrap($emptyDir, static function (GacelaConfig $config): void {
                $config->resetInMemoryCache();
                $config->setAppModulePaths(['inner']);
            });

            $tester = new CommandTester(new ListModulesCommand());
            $tester->execute([]);
            $display = $tester->getDisplay();

            self::assertStringContainsString('No modules found.', $display);
            self::assertStringContainsString('Scanned: inner', $display);
        } finally {
            // Names exactly what this test created.
            rmdir($emptyDir . '/inner');
            rmdir($emptyDir);
        }
    }

    public function test_non_matching_filter_reports_no_modules(): void
    {
        $this->command->execute(['filter' => 'NoSuchModuleXYZ']);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('No modules match filter "NoSuchModuleXYZ".', $output);
        self::assertStringNotContainsString('┌────', $output);
    }

    /**
     * A filter that matched nothing has the same question behind it as an empty
     * project: the answer depends on where discovery was pointed.
     */
    public function test_a_non_matching_filter_also_names_the_paths_that_were_scanned(): void
    {
        $this->command->execute(['filter' => 'NoSuchModuleXYZ']);

        self::assertStringContainsString('Scanned: ', $this->command->getDisplay());
    }

    public function test_non_matching_filter_reports_no_modules_in_detailed_view(): void
    {
        $this->command->execute(['filter' => 'NoSuchModuleXYZ', '--detailed' => true]);

        self::assertStringContainsString(
            'No modules match filter "NoSuchModuleXYZ".',
            $this->command->getDisplay(),
        );
    }

    /**
     * The project's module inventory, for something other than a reader.
     * `debug:module --json` describes the modules matching a filter and takes a
     * required argument, so asking it for "everything" meant inventing a
     * substring that happens to match everything -- which is not a question it
     * was built to answer, and quietly wrong for a project whose modules share
     * no common fragment.
     */
    public function test_json_describes_every_module_with_its_pillars(): void
    {
        $decoded = $this->listModulesAsJson([]);

        self::assertCount(3, $decoded);
        self::assertSame([
            'module' => 'TestModule1',
            'fullModuleName' => 'GacelaTest\\Feature\\Console\\ListModules\\TestModule1',
            'facade' => \GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1Facade::class,
            'factory' => \GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1Factory::class,
            'config' => null,
            'provider' => \GacelaTest\Feature\Console\ListModules\TestModule1\TestModule1Provider::class,
        ], $this->moduleNamed($decoded, 'TestModule1'));
    }

    /**
     * A pillar the module does not have is `null`, not an omitted key: the
     * shape is the same for every module, so a consumer reads a value rather
     * than probing for one. The table prints a blank cell for the same reason.
     */
    public function test_json_reports_a_missing_pillar_as_null(): void
    {
        $module = $this->moduleNamed($this->listModulesAsJson([]), 'TestModule2');

        self::assertNull($module['factory']);
        self::assertNull($module['config']);
        self::assertNull($module['provider']);
        self::assertIsString($module['facade']);
    }

    public function test_json_narrows_to_the_filter_like_the_table_does(): void
    {
        $decoded = $this->listModulesAsJson(['filter' => 'TestModule1']);

        self::assertCount(1, $decoded);
        self::assertSame('TestModule1', $decoded[0]['module']);
    }

    /**
     * The run a CI job most often gets, and the one that used to break it: a
     * filter matching nothing printed a sentence, so a consumer piping this to
     * a parser got a syntax error exactly when the answer was "none". An empty
     * list says the same thing and parses.
     */
    public function test_a_filter_matching_nothing_is_an_empty_document(): void
    {
        $tester = new CommandTester(new ListModulesCommand());
        $tester->execute(['filter' => 'NoSuchModuleXYZ', '--json' => true]);

        self::assertSame([], json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('No modules match', $tester->getDisplay());
    }

    /**
     * `--detailed` chooses between two ways of printing for a reader. A
     * consumer that asked for a document wants the fields either way, so the
     * flag has nothing to say about it.
     */
    public function test_detailed_makes_no_difference_to_the_document(): void
    {
        self::assertSame(
            $this->listModulesAsJson([]),
            $this->listModulesAsJson(['--detailed' => true]),
        );
    }

    public static function commandInputProvider(): iterable
    {
        yield 'slashes' => ['ListModules/TestModule1'];
        yield 'backward slashes' => ['ListModules\\TestModule1'];
    }

    /**
     * @param array<string, bool|string> $input
     *
     * @return list<array{module: string, fullModuleName: string, facade: string, factory: ?string, config: ?string, provider: ?string}>
     */
    private function listModulesAsJson(array $input): array
    {
        $tester = new CommandTester(new ListModulesCommand());
        $tester->execute($input + ['--json' => true]);

        /** @var list<array{module: string, fullModuleName: string, facade: string, factory: ?string, config: ?string, provider: ?string}> $decoded */
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param list<array{module: string, fullModuleName: string, facade: string, factory: ?string, config: ?string, provider: ?string}> $modules
     *
     * @return array{module: string, fullModuleName: string, facade: string, factory: ?string, config: ?string, provider: ?string}
     */
    private function moduleNamed(array $modules, string $name): array
    {
        foreach ($modules as $module) {
            if ($module['module'] === $name) {
                return $module;
            }
        }

        self::fail(sprintf('No module named "%s" in the document', $name));
    }

    /**
     * The single table row mentioning $module, so pillar assertions do not depend
     * on how wide the columns render.
     */
    private function rowFor(string $output, string $module): string
    {
        foreach (explode("\n", $output) as $line) {
            if (str_contains($line, $module)) {
                return $line;
            }
        }

        self::fail(sprintf('No row found for module "%s"', $module));
    }
}
