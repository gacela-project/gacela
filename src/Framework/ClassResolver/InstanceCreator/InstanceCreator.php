<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\InstanceCreator;

use Gacela\Framework\ClassResolver\DependencyResolver\DependencyResolver;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

final class InstanceCreator
{
    private GacelaConfigFileInterface $gacelaConfigFile;

    private ?DependencyResolver $dependencyResolver = null;

    public function __construct(GacelaConfigFileInterface $gacelaConfigFile)
    {
        $this->gacelaConfigFile = $gacelaConfigFile;
    }

    public function createByClassName(string $className): ?object
    {
        if (class_exists($className)) {
            // TODO: Consider caching the dependencies(?)
            $dependencies = $this->getDependencyResolver()
                ->resolveDependencies($className);

            /** @psalm-suppress MixedMethodCall */
            return new $className(...$dependencies);
        }

        return null;
    }

    private function getDependencyResolver(): DependencyResolver
    {
        if (null === $this->dependencyResolver) {
            $this->dependencyResolver = new DependencyResolver(
                $this->gacelaConfigFile
            );
        }

        return $this->dependencyResolver;
    }
}
