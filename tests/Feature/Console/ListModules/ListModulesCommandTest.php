<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ListModules;

use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

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

        $expected = <<<TXT
┌────────────────────────────────────────────────────────────┬────────┬─────────┬────────┬──────────┐
│ Module namespace                                           │ Facade │ Factory │ Config │ Provider │
├────────────────────────────────────────────────────────────┼────────┼─────────┼────────┼──────────┤
│ GacelaTest\Feature\Console\ListModules\LevelUp\TestModule3 │ x      │ x       │ x      │          │
│ GacelaTest\Feature\Console\ListModules\TestModule1         │ x      │ x       │        │ x        │
│ GacelaTest\Feature\Console\ListModules\TestModule2         │ x      │         │        │          │
└────────────────────────────────────────────────────────────┴────────┴─────────┴────────┴──────────┘

TXT;
        self::assertSame($expected, str_replace("\r\n", "\n", $this->command->getDisplay()));
    }

    public function test_list_modules_detailed(): void
    {
        $this->command->execute(['--detailed' => true]);

        $namespace = 'GacelaTest\\Feature\\Console\\ListModules';

        self::assertSame([
            '============================',
            '1.- TestModule3',
            '----------------------------',
            'Facade: ' . $namespace . '\\LevelUp\\TestModule3\\TestModule3Facade',
            'Factory: ' . $namespace . '\\LevelUp\\TestModule3\\TestModule3Factory',
            'Config: ' . $namespace . '\\LevelUp\\TestModule3\\TestModule3Config',
            // TestModule3 has no Provider, so the pillar renders as a blank cell.
            'Provider:',
            '============================',
            '2.- TestModule1',
            '----------------------------',
            'Facade: ' . $namespace . '\\TestModule1\\TestModule1Facade',
            'Factory: ' . $namespace . '\\TestModule1\\TestModule1Factory',
            'Config:',
            'Provider: ' . $namespace . '\\TestModule1\\TestModule1Provider',
            '============================',
            '3.- TestModule2',
            '----------------------------',
            'Facade: ' . $namespace . '\\TestModule2\\TestModule2Facade',
            'Factory:',
            'Config:',
            'Provider:',
            '',
        ], array_map(
            static fn (string $line): string => rtrim($line),
            explode("\n", str_replace("\r\n", "\n", $this->command->getDisplay())),
        ));
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
}
