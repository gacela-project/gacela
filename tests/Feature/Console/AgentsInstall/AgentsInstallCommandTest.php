<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\AgentsInstall;

use Gacela\Console\Infrastructure\Command\AgentsInstallCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function substr_count;
use function sys_get_temp_dir;
use function unlink;

final class AgentsInstallCommandTest extends TestCase
{
    private string $appRoot = '';

    protected function setUp(): void
    {
        $this->appRoot = sys_get_temp_dir() . '/gacela-agents-test-' . bin2hex(random_bytes(4));
        mkdir($this->appRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        self::assertStringStartsWith(sys_get_temp_dir() . '/gacela-agents-test-', $this->appRoot);

        if (is_file($this->agentsFile())) {
            unlink($this->agentsFile());
        }

        rmdir($this->appRoot);
    }

    public function test_it_creates_agents_md_pointing_at_the_shipped_guide(): void
    {
        $tester = $this->install();

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Created', $tester->getDisplay());
        self::assertStringContainsString('vendor/gacela-project/gacela/resources/agents/gacela.md', $this->agents());
    }

    public function test_the_guide_it_points_at_ships_with_the_package(): void
    {
        self::assertFileExists(__DIR__ . '/../../../../resources/agents/gacela.md');
    }

    public function test_it_appends_to_an_agents_md_the_project_wrote(): void
    {
        file_put_contents($this->agentsFile(), "# Our rules\n\nUse tabs.\n");

        $this->install();

        self::assertStringStartsWith("# Our rules\n\nUse tabs.\n\n<!-- gacela:start -->", $this->agents());
    }

    public function test_running_it_again_changes_nothing(): void
    {
        file_put_contents($this->agentsFile(), "# Our rules\n");
        $this->install();
        $first = $this->agents();

        $tester = $this->install();

        self::assertSame($first, $this->agents());
        self::assertStringContainsString('already points at the Gacela guide', $tester->getDisplay());
        self::assertSame(1, substr_count($this->agents(), '<!-- gacela:start -->'));
    }

    /**
     * An older block is replaced in place, and what surrounds it is the
     * project's, so it is left exactly as it was.
     */
    public function test_it_replaces_its_own_block_and_nothing_around_it(): void
    {
        file_put_contents($this->agentsFile(), "Before.\n\n<!-- gacela:start -->\nold text\n<!-- gacela:end -->\n\nAfter.\n");

        $tester = $this->install();

        self::assertStringContainsString('Updated', $tester->getDisplay());
        self::assertStringStartsWith("Before.\n\n<!-- gacela:start -->\n## Gacela", $this->agents());
        self::assertStringEndsWith("<!-- gacela:end -->\n\nAfter.\n", $this->agents());
        self::assertStringNotContainsString('old text', $this->agents());
    }

    public function test_an_empty_agents_md_gets_the_block_alone(): void
    {
        file_put_contents($this->agentsFile(), "\n");

        $this->install();

        self::assertStringStartsWith('<!-- gacela:start -->', $this->agents());
    }

    /**
     * Markers left by hand out of order are not a block. The command adds its
     * own, and the next run replaces only that one, never the text between a
     * stray marker and its block.
     */
    public function test_stray_markers_never_make_it_rewrite_the_projects_text(): void
    {
        file_put_contents($this->agentsFile(), "<!-- gacela:end -->\nOur text.\n<!-- gacela:start -->\nMore of ours.\n");

        $this->install();
        $this->install();

        self::assertStringStartsWith("<!-- gacela:end -->\nOur text.\n<!-- gacela:start -->\nMore of ours.\n\n<!-- gacela:start -->\n## Gacela", $this->agents());
    }

    public function test_a_long_block_is_replaced_once_and_the_projects_text_is_preserved(): void
    {
        $after = str_repeat("The project keeps this text.\n", 4000);
        file_put_contents($this->agentsFile(), "Before.\n<!-- gacela:start -->\n" . str_repeat('x', 100000) . "\n<!-- gacela:end -->\n" . $after);

        $this->install();
        $first = $this->agents();
        $tester = $this->install();

        self::assertSame($first, $this->agents());
        self::assertStringStartsWith("Before.\n<!-- gacela:start -->\n## Gacela", $first);
        self::assertStringEndsWith($after, $first);
        self::assertSame(1, substr_count($first, '<!-- gacela:end -->'));
        self::assertStringContainsString('already points at the Gacela guide', $tester->getDisplay());
    }

    public function test_a_long_stray_marker_gets_one_block_across_repeated_runs(): void
    {
        $project = "<!-- gacela:start -->\n" . str_repeat("The project keeps this text.\n", 4000);
        file_put_contents($this->agentsFile(), $project);

        $this->install();
        $first = $this->agents();
        $this->install();
        $tester = $this->install();

        self::assertSame($first, $this->agents());
        self::assertStringStartsWith($project . "\n<!-- gacela:start -->", $first);
        self::assertSame(1, substr_count($first, '<!-- gacela:end -->'));
        self::assertStringContainsString('already points at the Gacela guide', $tester->getDisplay());
    }

    public function test_a_stray_start_before_a_valid_block_leaves_intervening_text_alone(): void
    {
        $project = "<!-- gacela:start -->\nOur text stays here.\n";
        file_put_contents($this->agentsFile(), $project . "<!-- gacela:start -->\nold block\n<!-- gacela:end -->\nAfter.\n");

        $this->install();
        $first = $this->agents();
        $tester = $this->install();

        self::assertSame($first, $this->agents());
        self::assertStringStartsWith($project . "<!-- gacela:start -->\n## Gacela", $first);
        self::assertStringEndsWith("<!-- gacela:end -->\nAfter.\n", $first);
        self::assertStringNotContainsString('old block', $first);
        self::assertSame(1, substr_count($first, '<!-- gacela:end -->'));
        self::assertStringContainsString('already points at the Gacela guide', $tester->getDisplay());
    }

    private function install(): CommandTester
    {
        $tester = new CommandTester(new AgentsInstallCommand($this->appRoot));
        $tester->execute([]);

        return $tester;
    }

    private function agentsFile(): string
    {
        return $this->appRoot . '/AGENTS.md';
    }

    private function agents(): string
    {
        return (string) file_get_contents($this->agentsFile());
    }
}
