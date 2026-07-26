<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Framework\Health\HealthLevel;
use Gacela\Framework\Health\HealthStatus;

use function is_scalar;
use function sprintf;

/**
 * Adapts a module's {@see HealthStatus} to the doctor command's check contract.
 */
final class ModuleHealthCheck implements HealthCheck
{
    public function __construct(
        private readonly string $moduleName,
        private readonly HealthStatus $status,
    ) {
    }

    public function name(): string
    {
        return $this->moduleName;
    }

    public function run(): CheckResult
    {
        $title = sprintf('module health: %s', $this->moduleName);
        $detail = $this->status->message;
        $details = [$detail, ...$this->formatMetadata()];

        return match ($this->status->level) {
            HealthLevel::HEALTHY => CheckResult::ok($title, $detail),
            HealthLevel::DEGRADED => CheckResult::warn($title, $details),
            HealthLevel::UNHEALTHY => CheckResult::error($title, $details),
        };
    }

    /**
     * @return list<string>
     */
    private function formatMetadata(): array
    {
        $lines = [];

        /** @var mixed $value */
        foreach ($this->status->metadata as $key => $value) {
            $lines[] = sprintf('%s: %s', $key, is_scalar($value) ? (string) $value : get_debug_type($value));
        }

        return $lines;
    }
}
