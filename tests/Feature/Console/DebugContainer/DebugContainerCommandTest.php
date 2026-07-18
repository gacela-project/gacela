<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugContainer;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Infrastructure\Command\DebugContainerCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugContainerCommandTest extends TestCase
{
    private CommandTester $command;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $this->command = new CommandTester(new DebugContainerCommand());
    }

    public function test_stats_flag_takes_precedence_over_class_argument(): void
    {
        $this->command->execute(['class' => ConsoleFacade::class, '--stats' => true]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Container Statistics', $output);
        self::assertStringNotContainsString('Dependency Tree for', $output);
    }

    public function test_no_arguments_still_shows_stats(): void
    {
        $this->command->execute([]);

        self::assertStringContainsString('Container Statistics', $this->command->getDisplay());
    }

    public function test_class_argument_without_flag_shows_dependency_tree(): void
    {
        $this->command->execute(['class' => ConsoleFacade::class]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('Dependency Tree for', $output);
        self::assertStringNotContainsString('Container Statistics', $output);
    }
}
