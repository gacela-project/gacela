<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleD;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Benchmark\FileCache\ModuleD\Infra\EntityManager;
use GacelaTest\Benchmark\FileCache\ModuleD\Infra\Repository;

/**
 * @extends AbstractFacade<FactoryD>
 *
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 */
#[ServiceMap(method: 'getRepository', className: Repository::class)]
#[ServiceMap(method: 'getEntityManager', className: EntityManager::class)]
final class Facade extends AbstractFacade
{
    use ServiceResolverAwareTrait;

    public function loadGacelaCacheFile(): array
    {
        return [
            $this->getFactory()->getArrayConfigAndProvidedDependency(),
            $this->getRepository()->getAll(),
            $this->getEntityManager()->updateEntity(),
        ];
    }
}
