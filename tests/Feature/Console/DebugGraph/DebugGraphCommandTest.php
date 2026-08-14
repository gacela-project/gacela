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

    /** @var list<string> */
    private array $baselines = [];

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->command = new CommandTester(new DebugGraphCommand());
    }

    protected function tearDown(): void
    {
        foreach ($this->baselines as $baseline) {
            if (is_file($baseline)) {
                unlink($baseline);
            }
        }

        $this->baselines = [];
    }

    /**
     * `list:modules`, `debug:modules` and this one all report the same absence,
     * and did it in three different ways -- this one quoting a filter the
     * reader never typed. They share one message now, so the case a project
     * actually hits is worded once.
     */
    public function test_finding_nothing_without_a_filter_does_not_quote_an_empty_one(): void
    {
        $emptyDir = sys_get_temp_dir() . '/gacela-graph-empty-' . bin2hex(random_bytes(4));
        mkdir($emptyDir, 0777, true);

        try {
            Gacela::bootstrap($emptyDir, static function (GacelaConfig $config): void {
                $config->resetInMemoryCache();
                $config->setAppModulePaths(['.']);
            });

            $tester = new CommandTester(new DebugGraphCommand());
            $tester->execute([]);
            $display = $tester->getDisplay();

            self::assertStringContainsString('No modules found.', $display);
            self::assertStringNotContainsString('filter ""', $display);
        } finally {
            // Names exactly what this test created.
            rmdir($emptyDir);
        }
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

    public function test_compare_to_an_identical_graph_writes_nothing(): void
    {
        $this->command->execute(['--format' => 'json']);
        $baseline = $this->writeBaseline($this->command->getDisplay());

        $exitCode = $this->command->execute(['--compare-to' => $baseline]);

        self::assertSame(0, $exitCode);
        self::assertSame('', $this->command->getDisplay(), 'an unchanged graph must produce no report for CI to post');
    }

    public function test_compare_to_a_graph_missing_an_edge_reports_it_as_new(): void
    {
        $baseline = $this->writeBaseline(json_encode([
            self::MODULE_A => [],
            self::MODULE_B => [],
        ], JSON_THROW_ON_ERROR));

        $exitCode = $this->command->execute(['--compare-to' => $baseline]);

        $display = $this->command->getDisplay();
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('### Module dependency graph changed', $display);
        self::assertStringContainsString(sprintf('- **new dependency** `%s` → `%s`', self::MODULE_A, self::MODULE_B), $display);
        self::assertStringContainsString(
            str_replace('\\', '.', self::MODULE_A) . ' ==> ' . str_replace('\\', '.', self::MODULE_B),
            $display,
        );
    }

    public function test_compare_to_a_graph_with_an_extra_module_reports_it_as_removed(): void
    {
        $this->command->execute(['--format' => 'json']);
        /** @var array<string, list<string>> $current */
        $current = json_decode($this->command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        $current['GacelaTest\Feature\Console\DebugGraph\ModuleGone'] = [self::MODULE_A];

        $baseline = $this->writeBaseline(json_encode($current, JSON_THROW_ON_ERROR));

        $this->command->execute(['--compare-to' => $baseline]);

        $display = $this->command->getDisplay();
        self::assertStringContainsString('- **removed module** `GacelaTest\Feature\Console\DebugGraph\ModuleGone`', $display);
        self::assertStringContainsString('ModuleGone -.-> ', $display);
    }

    public function test_compare_to_a_missing_file_fails_instead_of_reporting_no_changes(): void
    {
        $exitCode = $this->command->execute(['--compare-to' => '/does/not/exist.json']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Cannot read the graph to compare to: "/does/not/exist.json"', $this->command->getDisplay());
    }

    public function test_compare_to_an_unparseable_file_fails(): void
    {
        $baseline = $this->writeBaseline('{not json');

        $exitCode = $this->command->execute(['--compare-to' => $baseline]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('is not valid JSON', $this->command->getDisplay());
    }

    public function test_compare_to_a_file_holding_a_json_scalar_fails(): void
    {
        // Valid JSON, but not a graph: json_decode() hands back an int.
        $baseline = $this->writeBaseline('123');

        $exitCode = $this->command->execute(['--compare-to' => $baseline]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString(
            sprintf('"%s" must contain a JSON array or object.', $baseline),
            $this->command->getDisplay(),
        );
    }

    private function writeBaseline(string $contents): string
    {
        $path = sys_get_temp_dir() . '/gacela-graph-baseline-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, $contents);
        $this->baselines[] = $path;

        return $path;
    }
}
