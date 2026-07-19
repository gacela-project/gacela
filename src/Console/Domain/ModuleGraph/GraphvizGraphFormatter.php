<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use function sprintf;

final class GraphvizGraphFormatter implements GraphFormatterInterface
{
    public function format(array $graph): string
    {
        $lines = ['digraph modules {'];

        foreach ($graph as $module => $dependencies) {
            if ($dependencies === []) {
                $lines[] = sprintf('    "%s";', $module);

                continue;
            }

            foreach ($dependencies as $dependency) {
                $lines[] = sprintf('    "%s" -> "%s";', $module, $dependency);
            }
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }
}
