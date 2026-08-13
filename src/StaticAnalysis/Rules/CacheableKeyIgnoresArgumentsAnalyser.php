<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;

use function sprintf;
use function str_contains;
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

        if ($key === null || str_contains($key, '{')) {
            return [];
        }

        return [
            new Violation(
                sprintf(
                    'The #[Cacheable] key "%s" on %s::%s() does not mention the arguments, '
                    . 'so every call shares one entry and the first result is served to all of them',
                    $key,
                    $class->name(),
                    $method->name->toString(),
                ),
                'gacela.cacheableKeyIgnoresArguments',
                sprintf('Put the argument in the key, as "%s:{0}", or drop `key` so the trait derives one from the arguments.', $key),
            ),
        ];
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
