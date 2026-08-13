<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;

use function count;
use function preg_match_all;
use function sprintf;
use function str_ends_with;

/**
 * A `#[Cacheable]` key that never mentions the arguments, on a method that has
 * them.
 *
 * The key decides what the entry is filed under, so one without a `{N}`
 * placeholder is the same string for every call: the first caller's result is
 * returned to all the others.
 *
 * ```php
 * #[Cacheable(ttl: 60, key: 'user')]
 * public function getUser(int $id): array   // getUser(2) answers with user 1
 * ```
 *
 * The attribute's own docblock calls this "rarely what you want when the method
 * takes parameters", which is the reason to say it here rather than leave it to
 * be discovered: nothing fails, and the wrong row is simply served.
 *
 * Two ways out, and the rule names both: put the argument in the key with
 * `{0}`, or drop `key` entirely -- with none, the trait derives one from the
 * method *and its arguments*, which is already per-argument.
 */
final class CacheableKeyIgnoresArgumentsAnalyser
{
    private const ATTRIBUTE = 'Cacheable';

    /**
     * @return list<Violation>
     */
    public function analyse(ClassMethod $method, AnalysedClassInterface $class): array
    {
        if ($method->params === []) {
            return [];
        }

        $key = $this->declaredKey($method);

        if ($key === null) {
            return [];
        }

        $indexes = $this->placeholderIndexes($key);

        if ($indexes === []) {
            return [$this->noPlaceholder($key, $class, $method)];
        }

        // A variadic takes as many arguments as the call site passes, so no
        // index can be shown to be out of range from the declaration alone.
        $count = count($method->params);
        if ($method->params[$count - 1]->variadic) {
            return [];
        }

        foreach ($indexes as $index) {
            if ($index < $count) {
                return [];
            }
        }

        return [$this->everyPlaceholderOutOfRange($key, $count, $class, $method)];
    }

    /**
     * `{N}` interpolates `$args[N] ?? ''`, so an index the method has no
     * argument for contributes an empty string -- the same constant on every
     * call. The key carries a `{`, which is all this rule used to ask for.
     */
    private function everyPlaceholderOutOfRange(
        string $key,
        int $count,
        AnalysedClassInterface $class,
        ClassMethod $method,
    ): Violation {
        return new Violation(
            sprintf(
                // One string, not a concatenation split for line length:
                // splitting generates Concat mutants that no reasonable
                // assertion kills, as `FALLBACK_DEPRECATION` says of the same
                // shape.
                'The #[Cacheable] key "%s" on %s::%s() has no placeholder within the %d argument%s the method takes, so every call shares one entry and the first result is served to all of them',
                $key,
                $class->name(),
                $method->name->toString(),
                $count,
                $count === 1 ? '' : 's',
            ),
            'gacela.cacheableKeyIgnoresArguments',
            sprintf('Use a placeholder the method has an argument for: {0} to {%d}.', $count - 1),
        );
    }

    private function noPlaceholder(
        string $key,
        AnalysedClassInterface $class,
        ClassMethod $method,
    ): Violation {
        return new Violation(
            sprintf(
                'The #[Cacheable] key "%s" on %s::%s() does not mention the arguments, so every call shares one entry and the first result is served to all of them',
                $key,
                $class->name(),
                $method->name->toString(),
            ),
            'gacela.cacheableKeyIgnoresArguments',
            sprintf('Put the argument in the key, as "%s:{0}", or drop `key` so the trait derives one from the arguments.', $key),
        );
    }

    /**
     * The indexes of the `{N}` placeholders, read with the pattern the trait
     * interpolates with -- `{id}` and a stray `{` are not placeholders there
     * and must not be counted as one here.
     *
     * Left as the digit strings the pattern captured: `\d+` cannot match
     * anything else, and PHP compares a numeric string to an int numerically,
     * so casting them first only added a step nothing could observe.
     *
     * @return list<numeric-string>
     */
    private function placeholderIndexes(string $key): array
    {
        preg_match_all('/\{(\d+)\}/', $key, $matches);

        return $matches[1];
    }

    /**
     * The literal `key:` of a `#[Cacheable]` on this method, or null when there
     * is no such attribute, no `key`, or one this cannot read.
     *
     * A key built at runtime -- a constant, a concatenation -- is not judged:
     * whether it carries a placeholder is not knowable here, and guessing would
     * report a project that is already correct.
     */
    private function declaredKey(ClassMethod $method): ?string
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (!$this->isCacheable($attribute->name->toString())) {
                    continue;
                }

                foreach ($attribute->args as $arg) {
                    if (!$arg->name instanceof Identifier) {
                        continue;
                    }

                    if ($arg->name->toString() === 'key' && $arg->value instanceof String_) {
                        return $arg->value->value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Written either way round: `#[Cacheable]` on an imported name, or
     * `#[Attribute\Cacheable]` on a partially qualified one.
     */
    private function isCacheable(string $name): bool
    {
        return $name === self::ATTRIBUTE || str_ends_with($name, '\\' . self::ATTRIBUTE);
    }
}
