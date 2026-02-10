<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\DependencyAnalyzer;

interface DependencyFormatterInterface
{
    /**
     * @param list<TModuleDependency> $dependencies
     */
    public function format(array $dependencies): string;
}
