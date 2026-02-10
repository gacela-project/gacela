<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleVersion;

final class TVersionCompatibilityResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly bool $isCompatible,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {
    }

    public function hasIssues(): bool
    {
        return $this->errors !== [] || $this->warnings !== [];
    }
}
