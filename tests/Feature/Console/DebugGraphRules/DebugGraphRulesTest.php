<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraphRules;

use Gacela\Console\Infrastructure\Command\DebugGraphCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function file_put_contents;
use function is_file;
use function json_decode;
use function json_encode;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class DebugGraphRulesTest extends TestCase
{
    private const PAYMENT = 'GacelaTest\Feature\Console\DebugGraphRules\Payment';

    private const ADMIN = 'GacelaTest\Feature\Console\DebugGraphRules\Admin';

    private CommandTester $command;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->command = new CommandTester(new DebugGraphCommand());
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->files = [];
    }

    public function test_check_fails_on_a_dependency_a_rule_denies(): void
    {
        $rules = $this->writeRules([
            ['from' => self::PAYMENT, 'deny' => [self::ADMIN], 'reason' => 'billing must not reach back-office'],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--rules' => $rules]);

        $display = $this->command->getDisplay();
        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Forbidden dependency:', $display);
        self::assertStringContainsString(self::PAYMENT . ' -> ' . self::ADMIN, $display);
        self::assertStringContainsString('billing must not reach back-office', $display);
    }

    public function test_check_passes_when_the_rules_permit_the_edges_that_exist(): void
    {
        $rules = $this->writeRules([
            ['from' => self::PAYMENT, 'allow' => [self::ADMIN], 'reason' => 'reviewed: payment reads the admin facade'],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--rules' => $rules]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No forbidden module dependencies', $this->command->getDisplay());
    }

    public function test_an_allow_list_forbids_the_edge_it_does_not_name(): void
    {
        $rules = $this->writeRules([
            ['from' => self::PAYMENT, 'allow' => [], 'reason' => 'payment is a leaf module'],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--rules' => $rules]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Forbidden dependency:', $this->command->getDisplay());
    }

    /**
     * The self-invalidating half. A rule about a module nobody has any more
     * reads as a boundary still being watched, which is the failure the cycle
     * allow list is already written against.
     */
    public function test_check_fails_on_a_rule_that_governs_no_module(): void
    {
        $rules = $this->writeRules([
            ['from' => self::PAYMENT, 'deny' => ['App\\Gone'], 'reason' => 'this module was deleted long ago'],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--rules' => $rules]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Module rule governs nothing:', $this->command->getDisplay());
        self::assertStringContainsString('App\\Gone', $this->command->getDisplay());
    }

    /**
     * A filtered graph cannot tell a stale rule from a module the filter
     * removed, so the two are not allowed to look alike.
     */
    public function test_rules_cannot_be_combined_with_a_filter(): void
    {
        $rules = $this->writeRules([
            ['from' => self::PAYMENT, 'deny' => [self::ADMIN], 'reason' => 'reviewed'],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--rules' => $rules, 'filter' => 'Payment']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('--rules cannot be combined with a filter', $this->command->getDisplay());
    }

    /**
     * Both files are read only by `--check`. Passing one without it printed the
     * graph and exited zero: a CI job that writes a rules file, wires the
     * command in, and is guarded by nothing.
     */
    public function test_rules_without_check_is_refused_rather_than_ignored(): void
    {
        $rules = $this->writeRules([
            ['from' => self::PAYMENT, 'deny' => [self::ADMIN], 'reason' => 'reviewed'],
        ]);

        $exitCode = $this->command->execute(['--rules' => $rules]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('--rules only applies with --check', $this->command->getDisplay());
        self::assertStringContainsString('--check --rules=', $this->command->getDisplay());
    }

    public function test_an_allow_list_without_check_is_refused_too(): void
    {
        $exitCode = $this->command->execute(['--allowed-cycles' => '/some/file.json']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('--allowed-cycles only applies with --check', $this->command->getDisplay());
    }

    /**
     * One file reads as one file, and the plural form on a single flag is the
     * seam that says nobody ran it.
     */
    public function test_both_together_are_named_in_the_plural(): void
    {
        $exitCode = $this->command->execute([
            '--rules' => '/some/rules.json',
            '--allowed-cycles' => '/some/cycles.json',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('--rules and --allowed-cycles only apply with --check', $this->command->getDisplay());
    }

    /**
     * The plain graph is the command's ordinary use and must stay unaffected.
     */
    public function test_a_plain_graph_run_is_unaffected(): void
    {
        $exitCode = $this->command->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringNotContainsString('only applies with --check', $this->command->getDisplay());
    }

    public function test_check_fails_when_the_rules_file_is_missing(): void
    {
        $exitCode = $this->command->execute(['--check' => true, '--rules' => '/does/not/exist.json']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Cannot read the module rules file', $this->command->getDisplay());
    }

    public function test_check_fails_when_a_rule_names_no_direction(): void
    {
        $rules = $this->writeRules([
            ['from' => self::PAYMENT, 'reason' => 'reviewed'],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--rules' => $rules]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('needs either "allow" or "deny"', $this->command->getDisplay());
    }

    public function test_check_fails_when_a_rule_has_no_reason(): void
    {
        $rules = $this->writeRules([
            ['from' => self::PAYMENT, 'deny' => [self::ADMIN]],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--rules' => $rules]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('needs a non-empty "reason"', $this->command->getDisplay());
    }

    public function test_the_json_report_carries_the_forbidden_dependencies(): void
    {
        $rules = $this->writeRules([
            ['from' => self::PAYMENT, 'deny' => [self::ADMIN], 'reason' => 'billing must not reach back-office'],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--rules' => $rules, '--format' => 'json']);

        /** @var array{forbiddenDependencies: list<array{from: string, to: string, reason: string}>, undeclaredCycles: list<mixed>} $report */
        $report = json_decode($this->command->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $exitCode);
        self::assertSame([], $report['undeclaredCycles']);
        self::assertSame([[
            'from' => self::PAYMENT,
            'to' => self::ADMIN,
            'reason' => 'billing must not reach back-office',
        ]], $report['forbiddenDependencies']);
    }

    /**
     * Silence, not a green line: a check that never ran must not report itself
     * as passing.
     */
    public function test_check_without_rules_says_nothing_about_module_rules(): void
    {
        $exitCode = $this->command->execute(['--check' => true]);

        self::assertSame(0, $exitCode);
        self::assertStringNotContainsString('forbidden module dependencies', $this->command->getDisplay());
    }

    /**
     * @param list<mixed> $entries
     */
    private function writeRules(array $entries): string
    {
        $path = sys_get_temp_dir() . '/gacela-module-rules-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode(['rules' => $entries], JSON_THROW_ON_ERROR));
        $this->files[] = $path;

        return $path;
    }
}
