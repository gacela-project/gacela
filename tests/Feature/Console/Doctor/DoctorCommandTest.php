<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\Doctor;

use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\Doctor\Fixtures\DegradedWithMetadataHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\DegradedWithoutMetadataHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\FakeHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\UnhealthyHealthCheck;
use GacelaTest\Feature\Console\Doctor\Fixtures\UnhealthyWithMetadataHealthCheck;
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

    public function test_doctor_renders_degraded_check_with_detail_and_every_metadata_line(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addHealthCheck(DegradedWithMetadataHealthCheck::class);
        });

        $command = new CommandTester(new DoctorCommand());
        $exitCode = $command->execute([]);

        $output = $command->getDisplay();

        self::assertStringContainsString('Cache is stale', $output);
        self::assertStringContainsString('stale-entries: 42', $output);
        self::assertStringContainsString('oldest-entry: 2020-01-01', $output);
        self::assertStringContainsString('raw-payload: array', $output);
        self::assertSame(Command::SUCCESS, $exitCode, 'degraded is a warning, not a failure');
    }

    public function test_doctor_renders_unhealthy_check_with_detail_and_every_metadata_line(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addHealthCheck(UnhealthyWithMetadataHealthCheck::class);
        });

        $command = new CommandTester(new DoctorCommand());
        $exitCode = $command->execute([]);

        $output = $command->getDisplay();

        self::assertStringContainsString('Broker unreachable', $output);
        self::assertStringContainsString('host: broker.internal', $output);
        self::assertStringContainsString('attempts: 3', $output);
        self::assertSame(Command::FAILURE, $exitCode);
    }

    public function test_doctor_renders_degraded_check_detail_when_there_is_no_metadata(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addHealthCheck(DegradedWithoutMetadataHealthCheck::class);
        });

        $command = new CommandTester(new DoctorCommand());
        $exitCode = $command->execute([]);

        self::assertStringContainsString('Slow response times', $command->getDisplay());
        self::assertSame(Command::SUCCESS, $exitCode);
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
