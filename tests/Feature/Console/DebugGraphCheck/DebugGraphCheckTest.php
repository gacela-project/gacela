<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugGraphCheck;

use Gacela\Console\Infrastructure\Command\DebugGraphCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function bin2hex;
use function file_put_contents;
use function is_file;
use function json_encode;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class DebugGraphCheckTest extends TestCase
{
    private const CYCLE_A = 'GacelaTest\Feature\Console\DebugGraphCheck\CycleA';

    private const CYCLE_B = 'GacelaTest\Feature\Console\DebugGraphCheck\CycleB';

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

    public function test_check_fails_on_an_undeclared_cycle(): void
    {
        $exitCode = $this->command->execute(['--check' => true]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Dependency cycle:', $this->command->getDisplay());
        self::assertStringContainsString(self::CYCLE_A . ' -> ' . self::CYCLE_B, $this->command->getDisplay());
    }

    public function test_check_passes_once_the_cycle_is_declared_with_a_reason(): void
    {
        $allowList = $this->writeAllowList([
            ['modules' => [self::CYCLE_A, self::CYCLE_B], 'reason' => 'reviewed: fixture for this very test'],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--allowed-cycles' => $allowList]);

        $display = $this->command->getDisplay();
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('allowed cycle:', $display);
        self::assertStringContainsString('reviewed: fixture for this very test', $display);
        self::assertStringContainsString('No undeclared module dependency cycles', $display);
    }

    /**
     * The allow list has to invalidate itself, or it stops being a record of a
     * decision and becomes a way to keep the check quiet.
     */
    public function test_check_fails_on_an_allowance_that_no_longer_matches_a_cycle(): void
    {
        $allowList = $this->writeAllowList([
            ['modules' => [self::CYCLE_A, self::CYCLE_B], 'reason' => 'real'],
            ['modules' => ['App\\Gone', 'App\\AlsoGone'], 'reason' => 'this cycle was fixed long ago'],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--allowed-cycles' => $allowList]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Allowed cycle no longer exists:', $this->command->getDisplay());
        self::assertStringContainsString('App\\AlsoGone -> App\\Gone', $this->command->getDisplay());
    }

    public function test_check_fails_when_the_allow_list_file_is_missing(): void
    {
        $exitCode = $this->command->execute(['--check' => true, '--allowed-cycles' => '/does/not/exist.json']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Cannot read the allowed cycles', $this->command->getDisplay());
    }

    public function test_check_fails_when_an_allowance_has_no_reason(): void
    {
        $allowList = $this->writeAllowList([
            ['modules' => [self::CYCLE_A, self::CYCLE_B], 'reason' => ''],
        ]);

        $exitCode = $this->command->execute(['--check' => true, '--allowed-cycles' => $allowList]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('needs a non-empty "reason"', $this->command->getDisplay());
    }

    public function test_without_check_the_command_still_just_prints_the_graph(): void
    {
        $exitCode = $this->command->execute([]);

        self::assertSame(0, $exitCode, 'debug:graph stays exit-code-neutral; --check is the gate');
        self::assertStringContainsString(self::CYCLE_A, $this->command->getDisplay());
    }

    /**
     * @param list<array{modules: list<string>, reason: string}> $entries
     */
    private function writeAllowList(array $entries): string
    {
        $path = sys_get_temp_dir() . '/gacela-cycles-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode($entries, JSON_THROW_ON_ERROR));
        $this->files[] = $path;

        return $path;
    }
}
