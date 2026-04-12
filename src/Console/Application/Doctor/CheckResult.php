<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor;

final class CheckResult
{
    /**
     * @param list<string> $details
     */
    private function __construct(
        public readonly CheckStatus $status,
        public readonly string $title,
        public readonly array $details,
        public readonly string $remediation,
    ) {
    }

    public static function ok(string $title, string $detail = ''): self
    {
        return new self(CheckStatus::Ok, $title, $detail === '' ? [] : [$detail], '');
    }

    /**
     * @param list<string> $details
     */
    public static function warn(string $title, array $details, string $remediation = ''): self
    {
        return new self(CheckStatus::Warn, $title, $details, $remediation);
    }

    /**
     * @param list<string> $details
     */
    public static function error(string $title, array $details, string $remediation = ''): self
    {
        return new self(CheckStatus::Error, $title, $details, $remediation);
    }
}
