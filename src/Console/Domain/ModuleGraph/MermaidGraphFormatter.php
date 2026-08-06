<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use function sprintf;
use function str_replace;

final class MermaidGraphFormatter implements GraphFormatterInterface
{
    public function format(array $graph): string
    {
        $lines = ['graph TD'];

        foreach ($graph as $module => $dependencies) {
            if ($dependencies === []) {
                $lines[] = sprintf('    %s', $this->nodeId($module));

                continue;
            }

            foreach ($dependencies as $dependency) {
                $lines[] = sprintf('    %s --> %s', $this->nodeId($module), $this->nodeId($dependency));
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function nodeId(string $module): string
    {
        return str_replace('\\', '.', $module);
    }
}
