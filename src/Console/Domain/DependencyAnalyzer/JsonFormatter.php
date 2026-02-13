<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\DependencyAnalyzer;

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class JsonFormatter implements DependencyFormatterInterface
{
    /**
     * @param list<TModuleDependency> $dependencies
     */
    public function format(array $dependencies): string
    {
        $data = [];

        foreach ($dependencies as $dep) {
            $data[] = [
                'module' => $dep->moduleName(),
                'dependencies' => $dep->dependencies(),
                'depth' => $dep->depth(),
            ];
        }

        return (string)json_encode(['modules' => $data], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
