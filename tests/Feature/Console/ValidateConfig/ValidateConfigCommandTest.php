<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig;

use Gacela\Console\Infrastructure\Command\ValidateConfigCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\SomeContract;
use GacelaTest\Feature\Console\ValidateConfig\Fixtures\UnrelatedImplementation;
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

    public function test_validate_config_is_silent_when_gacela_php_missing(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        // Missing gacela.php is optional; command must not mention it at all
        self::assertStringNotContainsString('gacela.php', $output);
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

    public function test_validate_config_explains_binding_mismatch(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(SomeContract::class, UnrelatedImplementation::class);
        });

        $command = new CommandTester(new ValidateConfigCommand());
        $command->execute([]);

        $output = $command->getDisplay();

        self::assertStringContainsString('Binding value may not be compatible with key', $output);
        self::assertStringContainsString('expected interface: ' . SomeContract::class, $output);
        self::assertStringContainsString('actual:', $output);
        self::assertStringContainsString('hint:', $output);
        self::assertStringContainsString('extend or implement ' . SomeContract::class, $output);
    }
}
