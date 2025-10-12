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
        self::assertSame($expected, $this->command->getDisplay());
    }

    public function test_list_modules_detailed(): void
    {
        $this->command->execute(['--detailed' => true]);

        $output = $this->command->getDisplay();

        // Verify this is the detailed view (not the table view)
        self::assertStringNotContainsString('┌────', $output, 'Should not contain table borders');
        self::assertStringContainsString('============================', $output, 'Should contain detailed view separators');
        self::assertStringContainsString('TestModule3Facade', $output);
        self::assertStringContainsString('TestModule1Factory', $output);
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

    public static function commandInputProvider(): iterable
    {
        yield 'slashes' => ['ListModules/TestModule1'];
        yield 'backward slashes' => ['ListModules\\TestModule1'];
    }
}
