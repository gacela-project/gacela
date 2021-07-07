<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithModulePrefix;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithoutModulePrefix;

final class ClassResolverFactory
{
    public function createClassNameFinder(): ClassNameFinderInterface
    {
        return new ClassNameFinder(
            new FinderRuleWithModulePrefix(),
            new FinderRuleWithoutModulePrefix(),
        );
    }
}
