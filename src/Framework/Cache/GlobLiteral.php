<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

use function array_fill_keys;
use function array_flip;
use function strpbrk;
use function strtr;

use const PHP_OS_FAMILY;

final class GlobLiteral
{
    private const ESCAPES = ['[' => '[[]', ']' => '[]]', '{' => '[{]', '}' => '[}]', '*' => '[*]', '?' => '[?]'];

    public static function escape(string $path): string
    {
        return strtr($path, self::escapes());
    }

    public static function unescape(string $pattern): string
    {
        return strtr($pattern, array_flip(self::escapes()));
    }

    public static function hasPattern(string $pattern): bool
    {
        return strpbrk(strtr($pattern, array_fill_keys(self::escapes(), '')), '*?[{') !== false;
    }

    /**
     * @return array<string, string>
     */
    private static function escapes(): array
    {
        return self::ESCAPES + ['\\' => PHP_OS_FAMILY === 'Windows' ? '\\' : '\\\\'];
    }
}
