<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class JsonGraphFormatter implements GraphFormatterInterface
{
    public function format(array $graph): string
    {
        return json_encode($graph, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
