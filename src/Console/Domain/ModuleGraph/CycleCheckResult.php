<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

final class CycleCheckResult
{
    /**
     * @param list<list<string>> $undeclaredCycles cycles nobody has signed off on
     * @param list<list<string>> $staleAllowances signed-off cycles that no longer exist
     */
    public function __construct(
        public readonly array $undeclaredCycles,
        public readonly array $staleAllowances,
    ) {
    }

    public function isClean(): bool
    {
        return $this->undeclaredCycles === [] && $this->staleAllowances === [];
    }
}
