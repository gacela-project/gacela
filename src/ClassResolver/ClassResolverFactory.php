<?php

declare(strict_types=1);

namespace Gacela\ClassResolver;

use Gacela\AbstractFactory;
use Gacela\ClassResolver\ClassNameCandidatesBuilder\ClassNameBuilder;
use Gacela\ClassResolver\ClassNameCandidatesBuilder\ClassNameCandidatesBuilderInterface;
use Gacela\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\ClassResolver\ClassNameFinder\ClassNameFinderInterface;

/**
 * @method ClassResolverConfig getConfig()
 */
final class ClassResolverFactory extends AbstractFactory
{
    public function createClassNameFinder(): ClassNameFinderInterface
    {
        return new ClassNameFinder(
            $this->createClassNameCandidatesBuilder()
        );
    }

    private function createClassNameCandidatesBuilder(): ClassNameCandidatesBuilderInterface
    {
        return new ClassNameBuilder($this->getConfig());
    }
}
