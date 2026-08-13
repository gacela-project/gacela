<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use function sprintf;

final class UseBlockParser
{
    /**
     * A `use` statement at the start of a line, with its optional alias.
     *
     * Anchored with `^` under `/m`, so a commented-out import (`// use ...`) and
     * one quoted inside a docblock are not statements this file makes. Line
     * endings never come into it, which is what the parser used to rewrite the
     * whole file for on Windows.
     */
    private const USE_STATEMENT = '#^\s*use\s+(?!function\s|const\s)([\\\\\w]+?)(?:\s+as\s+(\w+))?\s*;#mi';

    private const NAMESPACE_STATEMENT = '#^\s*namespace\s+([\\\\\w]+)\s*;#mi';

    public function getUseStatement(string $className, string $phpCode): string
    {
        if ($className === '' || $phpCode === '') {
            return '';
        }

        $fullyQualifiedClassName = $this->searchInUsesStatements($className, $phpCode);
        if ($fullyQualifiedClassName !== '') {
            return '\\' . ltrim($fullyQualifiedClassName, '\\');
        }

        $namespace = $this->lookInCurrentNamespace($phpCode);

        return sprintf('\\%s\\%s', $namespace, $className);
    }

    /**
     * The import whose *defined* name is `$className`, or an empty string when
     * this file imports no such name.
     *
     * Matched against the name the statement brings into scope -- its alias, or
     * its last segment -- rather than against the end of the line. Asking
     * whether the line contained `Facade;` meant every neighbouring import of
     * some `*Facade` answered for a module whose facade is called exactly
     * `Facade`, and an alias made it likelier still: a command reaching a
     * sibling module renames its facade to say which one it is, and
     * `OtherModuleFacade;` ends in `Facade;` too. Nothing failed -- the other
     * module's facade was injected instead.
     *
     * `use function` and `use const` import neither, so they cannot answer for
     * a class name that happens to match.
     */
    private function searchInUsesStatements(string $className, string $phpCode): string
    {
        preg_match_all(self::USE_STATEMENT, $phpCode, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if ($this->nameBroughtIntoScope($match) === $className) {
                return $match[1];
            }
        }

        return '';
    }

    /**
     * An alias replaces the name entirely: `use A\B\C as D;` makes `D` usable
     * and `C` not, which is why an aliased import does not answer for its
     * original short name.
     *
     * @param array<array-key, string> $match
     */
    private function nameBroughtIntoScope(array $match): string
    {
        $alias = $match[2] ?? '';

        if ($alias !== '') {
            return $alias;
        }

        $lastSeparator = strrpos($match[1], '\\');

        return $lastSeparator === false
            ? $match[1]
            : substr($match[1], $lastSeparator + 1);
    }

    private function lookInCurrentNamespace(string $phpCode): string
    {
        if (preg_match(self::NAMESPACE_STATEMENT, $phpCode, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
