<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugConfig;

use Gacela\Console\Infrastructure\Command\DebugConfigCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugConfigCommandTest extends TestCase
{
    private CommandTester $command;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfigKeyValue('app_name', 'gacela-demo');
            $config->addAppConfigKeyValue('debug_enabled', true);
        });

        $this->command = new CommandTester(new DebugConfigCommand());
    }

    public function test_shows_all_config_values(): void
    {
        $this->command->execute([]);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('app_name', $output);
        self::assertStringContainsString('gacela-demo', $output);
        self::assertStringContainsString('debug_enabled', $output);
        self::assertStringContainsString('true', $output);
    }

    public function test_filters_keys_by_substring(): void
    {
        $this->command->execute(['filter' => 'app_name']);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('app_name', $output);
        self::assertStringNotContainsString('debug_enabled', $output);
    }

    public function test_reports_when_no_keys_match_the_filter(): void
    {
        $this->command->execute(['filter' => 'nonexistent_key_xyz']);

        $output = $this->command->getDisplay();

        self::assertStringContainsString('No configuration keys match', $output);
    }
}
