<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor;

use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\Doctor\Fixtures\FakeHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\UnhealthyHealthCheck;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DoctorCommandTest extends TestCase
{
    public function test_doctor_runs_registered_health_check_class_string(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addHealthCheck(FakeHealthCheck::class);
        });

        $command = new CommandTester(new DoctorCommand());
        $exitCode = $command->execute([]);

        $output = $command->getDisplay();

        self::assertStringContainsString('FakeModule', $output);
        self::assertStringContainsString('FakeHealthCheck ran', $output);
        self::assertSame(Command::SUCCESS, $exitCode);
    }

    public function test_doctor_runs_registered_health_check_instance(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addHealthCheck(new FakeHealthCheck());
        });

        $command = new CommandTester(new DoctorCommand());
        $command->execute([]);

        $output = $command->getDisplay();

        self::assertStringContainsString('FakeModule', $output);
    }

    public function test_doctor_fails_when_registered_check_is_unhealthy(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addHealthCheck(UnhealthyHealthCheck::class);
        });

        $command = new CommandTester(new DoctorCommand());
        $exitCode = $command->execute([]);

        $output = $command->getDisplay();

        self::assertStringContainsString('UnhealthyModule', $output);
        self::assertStringContainsString('Service is down', $output);
        self::assertSame(Command::FAILURE, $exitCode);
    }

    public function test_doctor_without_registered_checks_still_succeeds(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        $command = new CommandTester(new DoctorCommand());
        $exitCode = $command->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
    }
}
