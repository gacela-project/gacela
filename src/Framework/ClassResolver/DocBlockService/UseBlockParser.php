<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

final class UseBlockParser
{
    public function getUseStatement(string $className, string $phpCode): string
    {
        if ($phpCode === '') {
            return '';
        }

        $needle = "{$className};";
        $lines = array_filter(
            explode(PHP_EOL, $phpCode),
            static fn (string $l) => str_contains($l, $needle)
        );
        /** @psalm-suppress RedundantCast */
        $lineSplit = (array)explode(' ', (string)reset($lines));

        return rtrim($lineSplit[1] ?? '', ';');
    }
}
