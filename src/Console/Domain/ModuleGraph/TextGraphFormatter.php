<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use function count;
use function sprintf;

final class TextGraphFormatter implements GraphFormatterInterface
{
    public function format(array $graph): string
    {
        $lines = [];

        foreach ($graph as $module => $dependencies) {
            $lines[] = sprintf('%s (%d)', $module, count($dependencies));
            foreach ($dependencies as $dependency) {
                $lines[] = sprintf('  -> %s', $dependency);
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
