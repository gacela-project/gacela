<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor;

use function is_string;

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

    /**
     * A check that found nothing wrong, and may still have something to say.
     *
     * Details and a remediation because not every finding is a problem: a check
     * reporting which files a rule of the framework's applies to has to name
     * them and say what to do if one of them is there by coincidence, and doing
     * that as a warning would fail `doctor --strict` on a correctly configured
     * project.
     *
     * @param string|list<string> $details
     */
    public static function ok(string $title, string|array $details = '', string $remediation = ''): self
    {
        if (is_string($details)) {
            $details = $details === '' ? [] : [$details];
        }

        return new self(CheckStatus::Ok, $title, $details, $remediation);
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
