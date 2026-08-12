<?php

declare(strict_types=1);

namespace Gacela\Framework\ServiceResolver;

use ReflectionAttribute;
use ReflectionClass;

/**
 * The one answer to "which pillar accessors does this class declare, and to
 * what class does each resolve".
 *
 * Both the runtime resolver and the PHPStan extension ask it, and the answer
 * has semantics beyond "read the attribute": the attribute is repeatable, the
 * first declaration of a method wins, and parents are never consulted because
 * attributes do not inherit. Re-derived per caller, those rules drift, and then
 * the analyser and the runtime disagree about the same line of code. Asked
 * here, they can only be differently stale.
 *
 * Callers memoize their own results. A cache here would have to be reset with
 * the rest of the framework's static state and would buy nothing: the runtime
 * already keys its memo by caller-and-method, and the analyser by class.
 *
 * @internal
 */
final class ServiceMapAccessors
{
    /**
     * The class one accessor resolves to, or null when this class declares no
     * such accessor.
     *
     * Kept separate from declaredOn() rather than reading a key out of it: this
     * is the runtime's cold-resolution path, and it stops at the accessor it
     * was asked for instead of instantiating the attributes nobody asked about.
     *
     * @param ReflectionClass<object> $reflectionClass
     *
     * @return class-string|null
     */
    public static function classNameFor(ReflectionClass $reflectionClass, string $method): ?string
    {
        foreach (self::serviceMapAttributes($reflectionClass) as $attribute) {
            /** @var ServiceMap $serviceMap */
            $serviceMap = $attribute->newInstance();

            if ($serviceMap->method === $method) {
                return $serviceMap->className;
            }
        }

        return null;
    }

    /**
     * Every accessor this class declares, in declaration order.
     *
     * @param ReflectionClass<object> $reflectionClass
     *
     * @return array<string, class-string> method name => resolved class
     */
    public static function declaredOn(ReflectionClass $reflectionClass): array
    {
        $accessors = [];

        foreach (self::serviceMapAttributes($reflectionClass) as $attribute) {
            /** @var ServiceMap $serviceMap */
            $serviceMap = $attribute->newInstance();

            // First declaration wins, matching classNameFor() and the runtime
            // it was extracted from. A later repeat of the same method is the
            // one that never resolves, so reporting it would describe a call
            // that does not happen.
            $accessors[$serviceMap->method] ??= $serviceMap->className;
        }

        return $accessors;
    }

    /**
     * Where the semantics live: this class only. getAttributes() does not walk
     * parents and neither does the runtime, so a subclass of a mapped class
     * declares nothing; being more generous here would type calls that fail.
     *
     * IS_INSTANCEOF is carried over from the readers this replaces. It matches
     * nothing extra while ServiceMap is final, and is what would keep them
     * agreeing if it ever stopped being.
     *
     * @param ReflectionClass<object> $reflectionClass
     *
     * @return list<ReflectionAttribute<ServiceMap>>
     */
    private static function serviceMapAttributes(ReflectionClass $reflectionClass): array
    {
        return $reflectionClass->getAttributes(ServiceMap::class, ReflectionAttribute::IS_INSTANCEOF);
    }
}
