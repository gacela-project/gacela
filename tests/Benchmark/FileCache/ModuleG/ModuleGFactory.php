<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleG;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Benchmark\FileCache\ModuleG\Infra\EntityManager;
use GacelaTest\Benchmark\FileCache\ModuleG\Infra\Repository;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @method ModuleGConfig getConfig()
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 */
#[ServiceMap(method: 'getConfig', className: ModuleGConfig::class)]
#[ServiceMap(method: 'getRepository', className: Repository::class)]
#[ServiceMap(method: 'getEntityManager', className: EntityManager::class)]
final class ModuleGFactory extends AbstractFactory
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
