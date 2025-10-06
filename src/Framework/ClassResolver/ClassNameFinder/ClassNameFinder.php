<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\Cache\CacheInterface;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameCachedFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameInvalidCandidateFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameNotFoundEvent;
use Gacela\Framework\Event\ClassResolver\ClassNameFinder\ClassNameValidCandidateFoundEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;

final class ClassNameFinder implements ClassNameFinderInterface
{
    use EventDispatchingCapabilities;

    /**
     * @param list<FinderRuleInterface> $finderRules
     * @param list<string> $projectNamespaces
     */
    public function __construct(
        private ClassValidatorInterface $classValidator,
        private array $finderRules,
        private CacheInterface $cache,
        private array $projectNamespaces,
    ) {
    }

    /**
     * @param list<string> $resolvableTypes
     *
     * @return class-string|null
     */
    public function findClassName(ClassInfo $classInfo, array $resolvableTypes): ?string
    {
        $cacheKey = $classInfo->getCacheKey();

        if ($this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            self::dispatchEvent(new ClassNameCachedFoundEvent($cacheKey, $cached));

            return $cached;
        }

        $projectNamespaces = $this->projectNamespaces;
        $projectNamespaces[] = $classInfo->getModuleNamespace();

        foreach ($projectNamespaces as $projectNamespace) {
            foreach ($this->finderRules as $finderRule) {
                foreach ($resolvableTypes as $resolvableType) {
                    $className = $finderRule->buildClassCandidate($projectNamespace, $resolvableType, $classInfo);

                    if ($this->classValidator->isClassNameValid($className)) {
                        $this->cache->put($cacheKey, $className);
                        self::dispatchEvent(new ClassNameValidCandidateFoundEvent($className));

                        return $className;
                    }

                    self::dispatchEvent(new ClassNameInvalidCandidateFoundEvent($className));
                }
            }
        }

        self::dispatchEvent(new ClassNameNotFoundEvent($classInfo, $resolvableTypes));

        return null;
    }
}
