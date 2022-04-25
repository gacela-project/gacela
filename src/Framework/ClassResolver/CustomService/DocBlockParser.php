<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\CustomService;

final class DocBlockParser
{
    public function getClassFromMethod(string $docBlock, string $method): string
    {
        if ($docBlock === '') {
            return '';
        }

        $lines = array_filter(
            explode(PHP_EOL, $docBlock),
            static fn (string $l) => str_contains($l, $method)
        );

        $lineSplit = (array)explode(' ', (string)reset($lines));

        return $lineSplit[3] ?? '';
    }
}
