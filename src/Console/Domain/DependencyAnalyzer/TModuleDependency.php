<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\DependencyAnalyzer;

use function count;

final class TModuleDependency
{
    /**
     * @param list<string> $dependencies
     */
    public function __construct(
        private readonly string $moduleName,
        private readonly array $dependencies,
        private readonly int $depth = 0,
    ) {
    }

    public function moduleName(): string
    {
        return $this->moduleName;
    }

    /**
     * @return list<string>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function depth(): int
    {
        return $this->depth;
    }

    public function hasDependencies(): bool
    {
        return count($this->dependencies) > 0;
    }
}
