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

    private ClassValidatorInterface $classValidator;

    /** @var list<FinderRuleInterface> */
    private array $finderRules;

    private CacheInterface $cache;

    /** @var list<string> */
    private array $projectNamespaces;

    /**
     * @param list<FinderRuleInterface> $finderRules
     * @param list<string> $projectNamespaces
     */
    public function __construct(
        ClassValidatorInterface $classValidator,
        array $finderRules,
        CacheInterface $cache,
        array $projectNamespaces
    ) {
        $this->classValidator = $classValidator;
        $this->finderRules = $finderRules;
        $this->cache = $cache;
        $this->projectNamespaces = $projectNamespaces;
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
            $this->dispatchEvent(new ClassNameCachedFoundEvent($cached));

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
                        $this->dispatchEvent(new ClassNameValidCandidateFoundEvent($className));

                        return $className;
                    }

                    $this->dispatchEvent(new ClassNameInvalidCandidateFoundEvent($className));
                }
            }
        }

        $this->dispatchEvent(new ClassNameNotFoundEvent($classInfo, $resolvableTypes));

        return null;
    }
}
