<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleE;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\DocBlockResolverAwareTrait;
use GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleE\Infra\EntityManager;
use GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleE\Infra\Repository;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @method ConfigE getConfig()
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 */
final class FactoryE extends AbstractFactory
{
    use DocBlockResolverAwareTrait;
    private StringValueInterface $stringValue;

    public function __construct(StringValueInterface $stringValue)
    {
        $this->stringValue = $stringValue;
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
