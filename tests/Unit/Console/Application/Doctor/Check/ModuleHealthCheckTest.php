<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\ModuleHealthCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Framework\Health\HealthStatus;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The documented bridge between `GacelaConfig::addHealthCheck()` and `doctor`:
 * a project's own check becomes a doctor check, and its level decides whether
 * the command passes.
 *
 * Nothing covered this, so `name()` sat at 0% while the integration it belongs
 * to is a documented feature with an exit code attached.
 */
final class ModuleHealthCheckTest extends TestCase
{
    public function test_a_healthy_module_passes(): void
    {
        $result = $this->check(HealthStatus::healthy('Database operational'))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertSame('module health: Database', $result->title);
        self::assertSame(['Database operational'], $result->details);
    }

    public function test_a_degraded_module_is_a_warning(): void
    {
        $result = $this->check(HealthStatus::degraded('High latency'))->run();

        self::assertSame(CheckStatus::Warn, $result->status);
    }

    /**
     * The level that makes `doctor` exit non-zero.
     */
    public function test_an_unhealthy_module_is_an_error(): void
    {
        $result = $this->check(HealthStatus::unhealthy('Database unreachable'))->run();

        self::assertSame(CheckStatus::Error, $result->status);
    }

    public function test_metadata_is_printed_under_the_message(): void
    {
        $result = $this->check(
            HealthStatus::unhealthy('Database unreachable', ['host' => 'db1', 'retries' => 3]),
        )->run();

        self::assertSame(['Database unreachable', 'host: db1', 'retries: 3'], $result->details);
    }

    /**
     * A passing check stays one line, whatever it was handed. Worth pinning
     * because `healthy()` takes metadata like the other two, so this is the one
     * level where passing it changes nothing.
     */
    public function test_a_healthy_module_reports_its_message_alone(): void
    {
        $result = $this->check(HealthStatus::healthy('All good', ['latency_ms' => 12]))->run();

        self::assertSame(['All good'], $result->details);
    }

    /**
     * Metadata reaches a console line, so a value that cannot be one is named by
     * type rather than crashing the report it appears in.
     */
    public function test_a_non_scalar_metadata_value_is_named_by_type(): void
    {
        $result = $this->check(
            HealthStatus::unhealthy('Broken', ['payload' => new stdClass(), 'ids' => [1, 2]]),
        )->run();

        self::assertSame(['Broken', 'payload: stdClass', 'ids: array'], $result->details);
    }

    /**
     * The name is the module, while the title the report prints is prefixed --
     * the doctor lists checks by name and renders by title.
     */
    public function test_the_check_is_named_after_the_module(): void
    {
        self::assertSame('Database', $this->check(HealthStatus::healthy())->name());
    }

    private function check(HealthStatus $status): ModuleHealthCheck
    {
        return new ModuleHealthCheck('Database', $status);
    }
}
