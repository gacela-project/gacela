<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Validate;

use Gacela\Console\Application\Doctor\CheckStatus;

/**
 * One of the three questions `validate:config` asks, with everything it found.
 */
final class ValidationSection
{
    /**
     * @param string $summary a plain line printed before the findings, blank
     *                        when the section has nothing to count
     * @param list<ValidationFinding> $findings
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $summary,
        public readonly array $findings,
    ) {
    }

    /**
     * The worst thing in the section, which is what decides whether the run
     * failed -- an `Ok` beside an `Error` does not soften it.
     */
    public function status(): CheckStatus
    {
        $status = CheckStatus::Ok;

        foreach ($this->findings as $finding) {
            if ($finding->status === CheckStatus::Error) {
                return CheckStatus::Error;
            }

            if ($finding->status === CheckStatus::Warn) {
                $status = CheckStatus::Warn;
            }
        }

        return $status;
    }
}
