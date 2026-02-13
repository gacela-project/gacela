<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleVersion;

final readonly class TVersionCompatibilityResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        public bool $isCompatible,
        public array $errors = [],
        public array $warnings = [],
    ) {
    }

    public function hasIssues(): bool
    {
        return $this->errors !== [] || $this->warnings !== [];
    }
}
