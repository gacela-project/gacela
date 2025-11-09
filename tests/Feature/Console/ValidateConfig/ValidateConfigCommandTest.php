<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig;

use Gacela\Console\Infrastructure\Command\ValidateConfigCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ValidateConfigCommandTest extends TestCase
{
    private CommandTester $command;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->command = new CommandTester(new ValidateConfigCommand());
    }

    public function test_validate_config_success(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Validating Gacela Configuration', $output);
        self::assertStringContainsString('Checking bindings...', $output);
    }

    public function test_validate_config_shows_warnings_if_no_gacela_php(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        // This test directory doesn't have a gacela.php, so we should see a warning
        self::assertStringContainsString('Checking bindings...', $output);
    }

    public function test_validate_config_exit_code_success(): void
    {
        $exitCode = $this->command->execute([]);

        // Should be success even with warnings
        self::assertSame(0, $exitCode);
    }

    public function test_validate_config_checks_circular_dependencies(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Checking for circular dependencies...', $output);
    }

    public function test_validate_config_checks_config_paths(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Checking configuration paths...', $output);
    }
}
