<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use function array_merge;
use function glob;
use function preg_match_all;
use function substr;

use const PHP_OS_FAMILY;
use const PREG_OFFSET_CAPTURE;

final class GlobPattern
{
    /**
     * @return list<string>
     */
    public static function matching(string $pattern): array
    {
        $group = self::firstGroup($pattern);
        if ($group === null) {
            return glob($pattern) ?: [];
        }

        [$start, $end, $commas] = $group;
        $matches = [];
        $previous = $start;
        foreach ([...$commas, $end] as $boundary) {
            $alternative = substr($pattern, $previous + 1, $boundary - $previous - 1);
            $expanded = substr($pattern, 0, $start) . $alternative . substr($pattern, $end + 1);
            $matches = array_merge($matches, self::matching($expanded));
            $previous = $boundary;
        }

        return $matches;
    }

    /**
     * @return array{int, int, list<int>}|null
     */
    private static function firstGroup(string $pattern): ?array
    {
        // Native brace expansion can interpret braces inside literal character classes.
        $escapes = PHP_OS_FAMILY === 'Windows' ? '' : '\\\\.(*SKIP)(*F)|';
        preg_match_all('~' . $escapes . '\[(?:[!^])?\]?[^\]]*\](*SKIP)(*F)|[{},]~', $pattern, $tokens, PREG_OFFSET_CAPTURE);
        $depth = 0;
        $start = 0;
        $commas = [];
        foreach ($tokens[0] as [$token, $offset]) {
            if ($token === '{' && $depth++ === 0) {
                $start = $offset;
            } elseif ($token === ',' && $depth === 1) {
                $commas[] = $offset;
            } elseif ($token === '}' && $depth > 0 && --$depth === 0) {
                return [$start, $offset, $commas];
            }
        }

        return null;
    }
}
