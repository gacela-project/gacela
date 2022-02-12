<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithModulePrefix;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithoutModulePrefix;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;

final class ClassResolverFactory
{
    private GacelaConfigFile $gacelaConfigFile;

    public function __construct(GacelaConfigFile $gacelaConfigFile)
    {
        $this->gacelaConfigFile = $gacelaConfigFile;
    }

    public function createClassNameFinder(): ClassNameFinderInterface
    {
        return new ClassNameFinder(
            [
                new FinderRuleWithModulePrefix(),
                new FinderRuleWithoutModulePrefix(),
            ],
            $this->gacelaConfigFile->getFlexibleServices()
        );
    }
}
