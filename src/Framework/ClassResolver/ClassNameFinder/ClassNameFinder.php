<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\ClassNameFinder;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;

final class ClassNameFinder implements ClassNameFinderInterface
{
    /** @var list<FinderRuleInterface> */
    private array $finderRules;

    /** @var list<string> */
    private $customServicePaths;

    /**
     * @param list<FinderRuleInterface> $finderRules
     * @param list<string> $customServicePaths
     */
    public function __construct(array $finderRules, array $customServicePaths)
    {
        $this->finderRules = $finderRules;
        $this->customServicePaths = $customServicePaths;
    }

    public function findClassName(ClassInfo $classInfo, string $resolvableType): ?string
    {
        foreach ($this->finderRules as $finderRule) {
            // First we look in the module-root dir
            $className = $finderRule->buildClassCandidate($classInfo, $resolvableType);
            if (class_exists($className)) {
                return $className;
            }

            // Otherwise, look for customServicePaths
            foreach ($this->customServicePaths as $customServicePath) {
                $className = $finderRule->buildClassCandidate(
                    $classInfo,
                    $resolvableType,
                    $customServicePath
                );

                if (class_exists($className)) {
                    return $className;
                }
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
