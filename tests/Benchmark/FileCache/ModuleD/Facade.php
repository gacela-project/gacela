<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleD;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Benchmark\FileCache\ModuleD\Infra\EntityManager;
use GacelaTest\Benchmark\FileCache\ModuleD\Infra\Repository;

/**
 * @extends AbstractFacade<FactoryD>
 *
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 */
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
