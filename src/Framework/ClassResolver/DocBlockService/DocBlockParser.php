<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

final class DocBlockParser
{
    public function getClassFromMethod(string $docBlock, string $method): string
    {
        if ($docBlock === '') {
            return '';
        }

        if (strcasecmp(substr(PHP_OS, 0, 3), 'WIN') === 0) {
            $docBlock = str_replace("\n", PHP_EOL, $docBlock);
        }

        $lines = array_filter(
            explode(PHP_EOL, $docBlock),
            static fn (string $l): bool => str_contains($l, $method),
        );

        /** @var array<int, string> $lineSplit */
        $lineSplit = explode(' ', (string)reset($lines));

        return $lineSplit[3] ?? '';
    }
}
