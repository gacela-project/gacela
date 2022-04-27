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

        $fullyQualifiedClassName = $this->searchInUsesStatements($className, $phpCode);
        if ($fullyQualifiedClassName !== '') {
            return $fullyQualifiedClassName;
        }

        $namespace = $this->lookInCurrentNamespace($phpCode);

        return sprintf('%s\\%s', $namespace, $className);
    }

    private function searchInUsesStatements(string $className, string $phpCode): string
    {
        $needle = "{$className};";

        $lines = array_filter(
            explode(PHP_EOL, $phpCode),
            static fn (string $l) => strpos($l, 'use ') === 0 && str_contains($l, $needle)
        );

        /** @psalm-suppress RedundantCast */
        $lineSplit = (array)explode(' ', (string)reset($lines));

        return rtrim($lineSplit[1] ?? '', ';');
    }

    private function lookInCurrentNamespace(string $phpCode): string
    {
        $lines = array_filter(
            explode(PHP_EOL, $phpCode),
            static fn (string $l) => strpos($l, 'namespace ') === 0
        );
        /** @psalm-suppress RedundantCast */
        $lineSplit = (array)explode(' ', (string)reset($lines));

        return rtrim($lineSplit[1] ?? '', ';');
    }
}
