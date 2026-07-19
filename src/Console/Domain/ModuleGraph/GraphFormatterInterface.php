<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

interface GraphFormatterInterface
{
    /**
     * @param array<string, list<string>> $graph module namespace => module namespaces it depends on
     */
    public function format(array $graph): string;
}
