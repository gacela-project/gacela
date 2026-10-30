<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\CacheableAttribute;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

use function in_array;
use function sprintf;

/**
 * Reports a `#[Cacheable]` method that never reaches `$this->cached()`.
 *
 * The attribute is metadata and nothing else: the caching happens inside
 * `cached()`, which reads it. A method carrying the attribute and not calling
 * it is not cached, is not reported by anything at runtime, and looks cached to
 * every reader -- the method says `#[Cacheable(ttl: 3600)]` right above its own
 * body. The first sign is a bill or a latency graph.
 *
 * Delegation is allowed, because the documentation describes it: `cached()` can
 * be called from a private helper, which then has to be passed `$method` and
 * `$args` explicitly, since the backtrace would otherwise land on the helper
 * and find no attribute there. So a method that reaches `cached()` through
 * siblings, however many hops away, is silent here.
 *
 * That is why this judges the class rather than the method: from a method alone
 * the helper is invisible, and a rule that could not see it would report the
 * documented pattern as a mistake.
 */
final class CacheableWithoutCachedCallAnalyser implements ClassAnalyserInterface
{
    private const CACHED = 'cached';

    /**
     * @return list<Violation>
     */
    public function analyse(ClassLike $node, AnalysedClassInterface $class): array
    {
        $cachingMethods = $this->methodsThatReachCached($node);

        $violations = [];

        foreach ($node->getMethods() as $method) {
            if (!$this->isCacheable($method)) {
                continue;
            }

            $name = $method->name->toString();

            if (in_array($name, $cachingMethods, true)) {
                continue;
            }

            $violations[] = new Violation(
                sprintf(
                    '%s::%s() carries #[Cacheable] and never calls $this->cached(), so nothing caches it: the attribute is metadata that `cached()` reads.',
                    $class->name(),
                    $name,
                ),
                'gacela.cacheableWithoutCachedCall',
                'Wrap the body in $this->cached(fn () => ...), or call a helper that does.',
                $method,
            );
        }

        return $violations;
    }

    /**
     * The methods that call `$this->cached()`, then every method that calls one
     * of those, until a pass adds nothing. The set only grows, so a helper cycle
     * ends the search instead of looping.
     *
     * @return list<string>
     */
    private function methodsThatReachCached(ClassLike $node): array
    {
        $names = [];
        $targets = [self::CACHED];

        do {
            $added = false;

            foreach ($node->getMethods() as $method) {
                $name = $method->name->toString();

                if (!in_array($name, $names, true) && $this->callsThisMethodNamed($method, $targets)) {
                    $names[] = $name;
                    $targets[] = $name;
                    $added = true;
                }
            }
        } while ($added);

        return $names;
    }

    /**
     * @param list<string> $names
     */
    private function callsThisMethodNamed(ClassMethod $method, array $names): bool
    {
        /** @var list<MethodCall> $calls */
        $calls = (new NodeFinder())->findInstanceOf($method, MethodCall::class);

        foreach ($calls as $call) {
            if (!$call->var instanceof Variable) {
                continue;
            }

            if ($call->var->name !== 'this') {
                continue;
            }

            if ($call->name instanceof Identifier && in_array($call->name->toString(), $names, true)) {
                return true;
            }
        }

        return false;
    }

    private function isCacheable(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (CacheableAttribute::isOn($attribute)) {
                    return true;
                }
            }
        }

        return false;
    }
}
