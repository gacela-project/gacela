<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleA;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Benchmark\FileCache\ModuleA\Infrastructure\EntityManager;
use GacelaTest\Benchmark\FileCache\ModuleA\Infrastructure\Repository;

/**
 * @extends AbstractFacade<FactoryA>
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
