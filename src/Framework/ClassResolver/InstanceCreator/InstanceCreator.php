<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\InstanceCreator;

use Gacela\Framework\ClassResolver\DependencyResolver\DependencyResolver;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

final class InstanceCreator
{
    private GacelaConfigFileInterface $gacelaConfigFile;

    private ?DependencyResolver $dependencyResolver = null;

    /** @var array<class-string,list<mixed>> */
    private array $cachedDependencies = [];

    public function __construct(GacelaConfigFileInterface $gacelaConfigFile)
    {
        $this->gacelaConfigFile = $gacelaConfigFile;
    }

    public function createByClassName(string $className): ?object
    {
        if (class_exists($className)) {
            if (!isset($this->cachedDependencies[$className])) {
                $this->cachedDependencies[$className] = $this
                    ->getDependencyResolver()
                    ->resolveDependencies($className);
            }

            /** @psalm-suppress MixedMethodCall */
            return new $className(...$this->cachedDependencies[$className]);
        }

        return null;
    }

    private function getDependencyResolver(): DependencyResolver
    {
        if ($this->dependencyResolver === null) {
            $this->dependencyResolver = new DependencyResolver(
                $this->gacelaConfigFile
            );
        }

        return $this->dependencyResolver;
    }
}
