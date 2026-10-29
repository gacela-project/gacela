<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use function preg_match;

final class DeclaredNamespace
{
    public static function of(string $fileContent): string
    {
        $comment = '(?:/\*.*?\*/|//[^\r\n]*|\#(?!\[)[^\r\n]*)';
        $header = '(?:^\s*|\A<\?php\s+)(?:(?:' . $comment . '|declare\s*\([^)]*\)\s*;)\s*)*';

        return preg_match('~' . $comment . '(*SKIP)(*F)|' . $header . 'namespace\s+([\w\\\\]+)\s*[;{]~ms', $fileContent, $matches) === 1
            ? $matches[1]
            : '';
    }
}
