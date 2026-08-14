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
        } finally {
            // Names exactly what this test created.
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

    public function test_non_matching_filter_reports_no_modules_in_detailed_view(): void
    {
        $this->command->execute(['filter' => 'NoSuchModuleXYZ', '--detailed' => true]);

        self::assertStringContainsString(
            'No modules match filter "NoSuchModuleXYZ".',
            $this->command->getDisplay(),
        );
    }

    public static function commandInputProvider(): iterable
    {
        yield 'slashes' => ['ListModules/TestModule1'];
        yield 'backward slashes' => ['ListModules\\TestModule1'];
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
