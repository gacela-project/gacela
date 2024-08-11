<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleG;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\DocBlockResolverAwareTrait;
use GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleG\Infra\EntityManager;
use GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleG\Infra\Repository;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @method ModuleGConfig getConfig()
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 */
final class ModuleGFactory extends AbstractFactory
{
    use DocBlockResolverAwareTrait;

    public function __construct(
        private StringValueInterface $stringValue,
    ) {
    }

    public function getArrayConfigAndProvidedDependency(): array
    {
        return [
            'config-key' => $this->getConfig()->getConfigValue(),
            'string-value' => $this->stringValue->value(),
            'provided-dependency' => $this->getProvidedDependency('provided-dependency'),
            'repository' => $this->getRepository()->getAll(),
            'entity-manager' => $this->getEntityManager()->updateEntity(),
        ];
    }
}
