<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;

final class ClassNameFinder implements ClassNameFinderInterface
{
    /** @var list<FinderRuleInterface> */
    private array $finderRules;

    /** @var array{paths?:list<string>,resolvable-types?:list<string>} */
    private $flexibleServices;

    /**
     * @param list<FinderRuleInterface> $finderRules
     * @param array{paths?:list<string>,resolvable-types?:list<string>} $flexibleServices
     */
    public function __construct(
        array $finderRules,
        array $flexibleServices
    ) {
        $this->finderRules = $finderRules;
        $this->flexibleServices = $flexibleServices;
    }

    public function findClassName(ClassInfo $classInfo, string $resolvableType): ?string
    {
        foreach ($this->finderRules as $finderRule) {
            // First look for flexibleServicePaths
            if ($resolvableType === 'FlexibleService') {
                foreach ($this->flexibleServices['paths'] ?? [] as $flexibleServicePath) {
                    foreach ($this->flexibleServices['resolvable-types'] ?? [] as $flexibleResolvableType) {
                        $className = $finderRule->buildClassCandidate(
                            $classInfo,
                            $flexibleResolvableType,
                            $flexibleServicePath
                        );

                        if (class_exists($className)) {
                            return $className;
                        }
                    }
                }
            }
            // Otherwise, we look in the module-root dir
            $className = $finderRule->buildClassCandidate($classInfo, $resolvableType);

            if (class_exists($className)) {
                return $className;
            }
        }

        //move 1 level up the classInfo and try again
        $upClassInfo = $classInfo->copyWith1LevelUpNamespace();
        if ($upClassInfo !== null) {
            return $this->findClassName($upClassInfo, $resolvableType);
        }

        return null;
    }
}
