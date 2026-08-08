<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleGraph;

use function count;
use function explode;
use function is_array;
use function preg_match;
use function preg_split;
use function token_get_all;
use function trim;

/**
 * Extracts the class imports a php file declares.
 *
 * The tokenizer is used for one thing only: finding the `use` statements that
 * sit at the top level. A `use` inside a class body imports a trait, which is a
 * different relationship, and collecting it would change the graph rather than
 * correct it. Everything after that is text handling, because the body of a use
 * statement is a comma-separated list with an optional `prefix{...}` group and
 * optional `as` aliases -- and nothing else.
 */
final class PhpImportParser
{
    /**
     * @return list<string> fully qualified class names, in declaration order
     */
    public function importsIn(string $phpSource): array
    {
        $imports = [];

        foreach ($this->topLevelUseStatements($phpSource) as $statement) {
            foreach ($this->namesIn($statement) as $name) {
                // `use \App\Billing\Invoice;` is legal and means the same import;
                // the leading separator is not part of the name modules match on.
                $imports[] = ltrim($name, '\\');
            }
        }

        return $imports;
    }

    /**
     * The text between each top-level `use` and its `;`.
     *
     * A grouped import's own braces are part of that text, so they are consumed
     * here rather than counted as a class body.
     *
     * @return list<string>
     */
    private function topLevelUseStatements(string $phpSource): array
    {
        $tokens = token_get_all($phpSource);
        $statements = [];
        $depth = 0;

        for ($index = 0, $total = count($tokens); $index < $total; ++$index) {
            $token = $tokens[$index];

            if ($token === '{') {
                ++$depth;
            } elseif ($token === '}') {
                --$depth;
            } elseif ($depth === 0 && is_array($token) && $token[0] === T_USE) {
                $statement = '';
                for (++$index; $index < $total && $tokens[$index] !== ';'; ++$index) {
                    $current = $tokens[$index];
                    $statement .= is_array($current) ? $current[1] : $current;
                }

                $statements[] = $statement;
            }
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    private function namesIn(string $statement): array
    {
        $statement = trim($statement);

        // `use function` / `use const` name a function or a constant, not a
        // class, and outside a group the modifier applies to every entry.
        if ($this->isNonClassImport($statement)) {
            return [];
        }

        if (preg_match('/^(.*?)\{(.*)}$/s', $statement, $matches) === 1) {
            // The prefix runs from the already-trimmed start to the brace, so
            // it carries no whitespace of its own.
            return $this->groupedNames($matches[1], $matches[2]);
        }

        $names = [];
        foreach (explode(',', $statement) as $entry) {
            $name = $this->nameOf($entry);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Inside a group php allows the modifier per entry, so a mixed group still
     * contributes its class entries.
     *
     * @return list<string>
     */
    private function groupedNames(string $prefix, string $body): array
    {
        $names = [];

        foreach (explode(',', $body) as $entry) {
            if ($this->isNonClassImport(trim($entry))) {
                continue;
            }

            $name = $this->nameOf($entry);
            if ($name !== '') {
                $names[] = $prefix . $name;
            }
        }

        return $names;
    }

    /**
     * The keywords are case-insensitive and any whitespace may follow them, so
     * `use FUNCTION App\helper;` is the same statement as the lowercase form.
     * Requiring the whitespace keeps a class named `Functional` a class.
     */
    private function isNonClassImport(string $entry): bool
    {
        return preg_match('/^(?:function|const)\s/i', $entry) === 1;
    }

    /**
     * The imported name, with any `as` alias dropped -- the alias renames the
     * import locally and says nothing about which module it came from.
     */
    private function nameOf(string $entry): string
    {
        // Split on the alias keyword, which consumes the whitespace around it,
        // so only the entry's own outer padding has to go.
        $parts = preg_split('/\s+as\s+/i', trim($entry));

        return $parts === false ? '' : $parts[0];
    }
}
