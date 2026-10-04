<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use PhpToken;

use function count;
use function in_array;
use function ltrim;
use function str_contains;
use function str_starts_with;

/**
 * What one PHP file declares, read from its tokens: the classes and traits it
 * names, and whether it can refer to Gacela's attributes at all.
 *
 * Tokens rather than a pattern, because the result is output: a regex misread
 * a braced namespace, a keyword in another case, or `class` in a comment, and
 * a class it misses is a member silently lost.
 *
 * @internal
 */
final class SourceDeclarations
{
    private const ATTRIBUTE_NAMESPACE = 'Gacela\\Framework\\Attribute\\';

    /**
     * @param list<string> $classes fully qualified, in the order declared
     * @param list<string> $traits fully qualified, in the order declared
     */
    private function __construct(
        public readonly array $classes,
        public readonly array $traits,
        public readonly bool $namesAttributeNamespace,
    ) {
    }

    public static function of(string $source): self
    {
        $tokens = PhpToken::tokenize($source);
        $namespace = '';
        $classes = [];
        $traits = [];
        $names = str_contains($source, self::ATTRIBUTE_NAMESPACE);

        foreach ($tokens as $index => $token) {
            if ($token->is(T_NAMESPACE)) {
                $next = self::significant($tokens, $index, 1);
                if ($next instanceof PhpToken && $next->is([T_STRING, T_NAME_QUALIFIED])) {
                    $namespace = $next->text;
                } elseif ($next instanceof PhpToken && $next->text === '{') {
                    $namespace = '';
                }

                continue;
            }

            if ($token->is(T_USE) && !$names) {
                $names = self::importsAttributeNamespace($tokens, $index);

                continue;
            }

            if ($token->is(T_CLASS)) {
                $name = self::significant($tokens, $index, 1);
                $previous = self::significant($tokens, $index, -1);
                if ($name instanceof PhpToken && $name->is(T_STRING)
                    && (!$previous instanceof PhpToken || !$previous->is([T_DOUBLE_COLON, T_NULLSAFE_OBJECT_OPERATOR, T_OBJECT_OPERATOR]))
                ) {
                    $classes[] = ($namespace === '' ? '' : $namespace . '\\') . $name->text;
                }

                continue;
            }

            if ($token->is(T_TRAIT)) {
                $name = self::significant($tokens, $index, 1);
                if ($name instanceof PhpToken && $name->is(T_STRING)) {
                    $traits[] = ($namespace === '' ? '' : $namespace . '\\') . $name->text;
                }
            }
        }

        return new self($classes, $traits, $names);
    }

    /**
     * An import of an attribute, grouped or not, or of a namespace above them
     * under an alias: `use Gacela\Framework\{Attribute\Tag}`, `use
     * Gacela\Framework as G`.
     *
     * @param array<PhpToken> $tokens
     */
    private static function importsAttributeNamespace(array $tokens, int $use): bool
    {
        $prefix = '';
        $count = count($tokens);

        for ($i = $use + 1; $i < $count; ++$i) {
            $token = $tokens[$i];

            if ($token->text === ';' || $token->text === '(') {
                return false;
            }

            if ($token->text === '{') {
                $prefix = self::lastName($tokens, $i);

                continue;
            }

            if (!$token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                continue;
            }

            $previous = self::significant($tokens, $i, -1);
            if ($previous instanceof PhpToken && $previous->is(T_AS)) {
                continue;
            }

            $next = self::significant($tokens, $i, 1);
            if ($next instanceof PhpToken && $next->is(T_NS_SEPARATOR)) {
                continue;
            }

            $imported = ltrim(($prefix === '' ? '' : $prefix . '\\') . $token->text, '\\') . '\\';
            if (str_starts_with($imported, self::ATTRIBUTE_NAMESPACE) || str_starts_with(self::ATTRIBUTE_NAMESPACE, $imported)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<PhpToken> $tokens
     */
    private static function lastName(array $tokens, int $brace): string
    {
        for ($i = $brace - 1; $i >= 0; --$i) {
            if ($tokens[$i]->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                return ltrim($tokens[$i]->text, '\\');
            }
        }

        return '';
    }

    /**
     * @param array<PhpToken> $tokens
     */
    private static function significant(array $tokens, int $from, int $step): ?PhpToken
    {
        for ($i = $from + $step; isset($tokens[$i]); $i += $step) {
            if (!in_array($tokens[$i]->id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $tokens[$i];
            }
        }

        return null;
    }
}
