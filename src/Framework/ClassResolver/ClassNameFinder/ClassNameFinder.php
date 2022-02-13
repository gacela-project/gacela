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
    private $customServicesLocation;

    /**
     * @param list<FinderRuleInterface> $finderRules
     * @param list<string> $customServicesLocation
     */
    public function __construct(array $finderRules, array $customServicesLocation)
    {
        $this->finderRules = $finderRules;
        $this->customServicesLocation = $customServicesLocation;
    }

    public function findClassName(ClassInfo $classInfo, string $resolvableType): ?string
    {
        foreach ($this->finderRules as $finderRule) {
            // First we look in the module-root dir
            $className = $finderRule->buildClassCandidate($classInfo, $resolvableType);
            if (class_exists($className)) {
                return $className;
            }

            // Otherwise, look for customServicesLocation
            foreach ($this->customServicesLocation as $customServicePath) {
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
