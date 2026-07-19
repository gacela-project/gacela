<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraph;

use Gacela\Console\Infrastructure\Command\DebugGraphCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function sprintf;

final class DebugGraphCommandTest extends TestCase
{
    private const MODULE_A = 'GacelaTest\Feature\Console\DebugGraph\ModuleA';

    private const MODULE_B = 'GacelaTest\Feature\Console\DebugGraph\ModuleB';

    private CommandTester $command;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->command = new CommandTester(new DebugGraphCommand());
    }

    public function test_text_format_lists_modules_and_edges(): void
    {
        $this->command->execute([]);

        $display = $this->command->getDisplay();
        self::assertStringContainsString(self::MODULE_A . ' (1)', $display);
        self::assertStringContainsString('  -> ' . self::MODULE_B, $display);
        self::assertStringContainsString(self::MODULE_B . ' (0)', $display);
    }

    public function test_mermaid_format(): void
    {
        $this->command->execute(['--format' => 'mermaid']);

        $display = $this->command->getDisplay();
        self::assertStringContainsString('graph TD', $display);
        self::assertStringContainsString(
            str_replace('\\', '.', self::MODULE_A) . ' --> ' . str_replace('\\', '.', self::MODULE_B),
            $display,
        );
    }

    public function test_graphviz_format(): void
    {
        $this->command->execute(['--format' => 'graphviz']);

        $display = $this->command->getDisplay();
        self::assertStringContainsString('digraph modules {', $display);
        self::assertStringContainsString(sprintf('"%s" -> "%s";', self::MODULE_A, self::MODULE_B), $display);
        self::assertStringContainsString('}', $display);
    }

    public function test_json_format_is_parseable(): void
    {
        $this->command->execute(['--format' => 'json']);

        /** @var array<string, list<string>> $graph */
        $graph = json_decode($this->command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([self::MODULE_B], $graph[self::MODULE_A]);
        self::assertSame([], $graph[self::MODULE_B]);
    }

    public function test_unknown_format_fails(): void
    {
        $exitCode = $this->command->execute(['--format' => 'nope']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unknown format "nope"', $this->command->getDisplay());
    }

    public function test_filter_without_matches_prints_comment(): void
    {
        $exitCode = $this->command->execute(['filter' => 'DoesNotExist']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No modules match filter "DoesNotExist"', $this->command->getDisplay());
    }
}
