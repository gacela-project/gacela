<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use function count;
use function in_array;
use function is_array;
use function ltrim;
use function preg_match;
use function rtrim;
use function sprintf;
use function strrpos;
use function substr;

use function token_get_all;

use const T_AS;
use const T_CLASS;
use const T_COMMENT;
use const T_CONST;
use const T_DOC_COMMENT;
use const T_ENUM;
use const T_FUNCTION;
use const T_INTERFACE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_NS_SEPARATOR;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

final class UseBlockParser
{
    private const NAMESPACE_STATEMENT = '#^\s*namespace\s+([\\\\\w]+)\s*;#mi';

    /** Tokens whose text is part of a name being imported. */
    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    private const IGNORED_TOKENS = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    /**
     * Imports precede the first declaration, so stopping at one keeps
     * `class X { use SomeTrait; }` and `function () use ($x)` -- both spelled
     * with the same keyword -- out of the map entirely.
     */
    private const DECLARATION_TOKENS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION];

    public function getUseStatement(string $className, string $phpCode): string
    {
        if ($className === '' || $phpCode === '') {
            return '';
        }

        $imports = $this->importsOf($phpCode);
        if (isset($imports[$className])) {
            return '\\' . ltrim($imports[$className], '\\');
        }

        $namespace = $this->lookInCurrentNamespace($phpCode);

        return sprintf('\\%s\\%s', $namespace, $className);
    }

    /**
     * Every name this file brings into scope, mapped to what it names.
     *
     * Tokenised rather than matched. A pattern can read `use A\B;` and even an
     * alias, but not `use A\B\{C, D};` or `use A\B, C\D;` -- and a grouped
     * import that matched nothing did not fail: it fell back to the caller's
     * own namespace, so a class of the same short name sitting there was
     * injected instead. PSR-12 groups are ordinary, and formatters wrap them
     * across lines, which a line-anchored pattern cannot see past either.
     *
     * The result becomes an injected object, which is when this repository
     * spends tokens instead of a regex: a wrong match here is not a wasted
     * lookup, it is the wrong service.
     *
     * @return array<string, string> name in scope => fully qualified name
     */
    private function importsOf(string $phpCode): array
    {
        $tokens = token_get_all($phpCode);
        $imports = [];
        $position = 0;
        $count = count($tokens);

        while ($position < $count) {
            $token = $tokens[$position];

            // Imports precede the first declaration, so stopping here keeps
            // `class X { use SomeTrait; }` and `function () use ($x)` -- both
            // spelled with the same keyword -- out of the map entirely.
            if (is_array($token) && in_array($token[0], self::DECLARATION_TOKENS, true)) {
                break;
            }

            if (is_array($token) && $token[0] === T_USE) {
                $position = $this->readUseStatement($tokens, $position + 1, $imports);
                continue;
            }

            ++$position;
        }

        return $imports;
    }

    /**
     * Reads one `use` statement into $imports and answers where it ended.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     * @param array<string, string> $imports
     */
    private function readUseStatement(array $tokens, int $position, array &$imports): int
    {
        $count = count($tokens);
        $prefix = '';
        $name = '';
        $alias = '';
        $expectingAlias = false;

        while ($position < $count) {
            $token = $tokens[$position];
            ++$position;

            if (is_array($token)) {
                if (in_array($token[0], self::IGNORED_TOKENS, true)) {
                    continue;
                }

                // `use function`/`use const` import neither a class nor a name
                // that can answer for one, grouped or not.
                if ($token[0] === T_FUNCTION || $token[0] === T_CONST) {
                    return $this->skipToEndOfStatement($tokens, $position);
                }

                if ($token[0] === T_AS) {
                    $expectingAlias = true;
                    continue;
                }

                if (in_array($token[0], self::NAME_TOKENS, true)) {
                    if ($expectingAlias) {
                        $alias = $token[1];
                    } else {
                        $name .= $token[1];
                    }
                }

                continue;
            }

            if ($token === '{') {
                $prefix = $name;
                $name = '';
                continue;
            }

            if (in_array($token, [',', '}', ';'], true)) {
                $this->remember($imports, $prefix, $name, $alias);
                $name = '';
                $alias = '';
                $expectingAlias = false;

                if ($token === '}') {
                    $prefix = '';
                }

                if ($token === ';') {
                    return $position;
                }
            }
        }

        return $position;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function skipToEndOfStatement(array $tokens, int $position): int
    {
        $count = count($tokens);

        while ($position < $count) {
            if ($tokens[$position] === ';') {
                return $position + 1;
            }

            ++$position;
        }

        return $position;
    }

    /**
     * An alias replaces the name entirely: `use A\B\C as D;` makes `D` usable
     * and `C` not, which is why an aliased import does not answer for its
     * original short name.
     *
     * The first import of a name wins, matching the pattern this replaced --
     * two imports defining the same name is illegal PHP anyway.
     *
     * @param array<string, string> $imports
     */
    private function remember(array &$imports, string $prefix, string $name, string $alias): void
    {
        if ($name === '') {
            return;
        }

        $fullyQualified = $prefix === ''
            ? $name
            : rtrim($prefix, '\\') . '\\' . ltrim($name, '\\');

        $inScope = $alias !== '' ? $alias : $this->lastSegmentOf($name);

        if (!isset($imports[$inScope])) {
            $imports[$inScope] = $fullyQualified;
        }
    }

    private function lastSegmentOf(string $name): string
    {
        $lastSeparator = strrpos($name, '\\');

        return $lastSeparator === false
            ? $name
            : substr($name, $lastSeparator + 1);
    }

    private function lookInCurrentNamespace(string $phpCode): string
    {
        if (preg_match(self::NAMESPACE_STATEMENT, $phpCode, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
