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

        // A persisted entry outlives the class it names: rename or delete a
        // Factory and deploy without clearing the cache dir, and the hit still
        // resolves to a class that is gone. Nothing downstream catches it --
        // the container answers `null` for a class it cannot find, and
        // `AbstractClassResolver::createInstance()` is typed to return an
        // object -- so the run dies on a TypeError naming neither the class,
        // the cache, nor `cache:clear`.
        //
        // Checking costs nothing on the warm path: ClassValidator memoizes
        // class_exists, and a name that survives this is about to be autoloaded
        // anyway to be instantiated.
        if ($this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);

            if ($this->classValidator->isClassNameValid($cached)) {
                if (self::shouldDispatch(ClassNameCachedFoundEvent::class)) {
                    self::dispatchEvent(new ClassNameCachedFoundEvent($cacheKey, $cached));
                }

                return $cached;
            }

            // Stale: fall through to the rules below, whose `put()` overwrites
            // the entry, so the next resolution does not pay for this miss.
        }

        $projectNamespaces = $this->projectNamespaces;
        $projectNamespaces[] = $classInfo->getModuleNamespace();

        foreach ($projectNamespaces as $projectNamespace) {
            foreach ($this->finderRules as $finderRule) {
                foreach ($resolvableTypes as $resolvableType) {
                    $className = $finderRule->buildClassCandidate($projectNamespace, $resolvableType, $classInfo);

                    if ($this->classValidator->isClassNameValid($className)) {
                        $this->cache->put($cacheKey, $className);
                        if (self::shouldDispatch(ClassNameValidCandidateFoundEvent::class)) {
                            self::dispatchEvent(new ClassNameValidCandidateFoundEvent($className));
                        }

                        return $className;
                    }

                    if (self::shouldDispatch(ClassNameInvalidCandidateFoundEvent::class)) {
                        self::dispatchEvent(new ClassNameInvalidCandidateFoundEvent($className));
                    }
                }
            }
        }

        if (self::shouldDispatch(ClassNameNotFoundEvent::class)) {
            self::dispatchEvent(new ClassNameNotFoundEvent($classInfo, $resolvableTypes));
        }

        return null;
    }
}
