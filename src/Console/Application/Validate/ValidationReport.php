<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Validate;

use Gacela\Console\Application\Doctor\CheckStatus;

/**
 * What one `validate:config` run found, before anyone decides how to say it.
 */
final class ValidationReport
{
    /**
     * @param list<ValidationSection> $sections
     */
    public function __construct(
        public readonly array $sections,
        public readonly string $configFile = '',
    ) {
    }

    public function status(): CheckStatus
    {
        $status = CheckStatus::Ok;

        foreach ($this->sections as $section) {
            $sectionStatus = $section->status();

            if ($sectionStatus === CheckStatus::Error) {
                return CheckStatus::Error;
            }

            if ($sectionStatus === CheckStatus::Warn) {
                $status = CheckStatus::Warn;
            }
        }

        return $status;
    }

    public function hasErrors(): bool
    {
        return $this->status() === CheckStatus::Error;
    }

    public function hasWarnings(): bool
    {
        return $this->status() === CheckStatus::Warn;
    }

    /**
     * The statuses are the values `CheckStatus` already carries -- `ok`, `warn`,
     * `error` -- rather than a second vocabulary invented for this report, so a
     * job reading `doctor --format=json` and this one reads the same words.
     *
     * @return array{status: string, configFile: string, checks: list<array{
     *     name: string,
     *     status: string,
     *     findings: list<array{status: string, message: string, details: list<string>}>
     * }>}
     */
    public function toArray(): array
    {
        $checks = [];

        foreach ($this->sections as $section) {
            $findings = [];
            foreach ($section->findings as $finding) {
                $findings[] = [
                    'status' => $finding->status->value,
                    'message' => $finding->message(),
                    'details' => $finding->details,
                ];
            }

            $checks[] = [
                'name' => $section->name,
                'status' => $section->status()->value,
                'findings' => $findings,
            ];
        }

        return [
            'status' => $this->status()->value,
            'configFile' => $this->configFile,
            'checks' => $checks,
        ];
    }
}
