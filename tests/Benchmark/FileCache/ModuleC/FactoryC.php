<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleC;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Benchmark\FileCache\ModuleC\Infra\EntityManager;
use GacelaTest\Benchmark\FileCache\ModuleC\Infra\Repository;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @method ConfigC getConfig()
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 */
final class FactoryC extends AbstractFactory
{
    use ServiceResolverAwareTrait;

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
