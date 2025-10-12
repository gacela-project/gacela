<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleG;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\DocBlockResolverAwareTrait;
use GacelaTest\Benchmark\FileCache\ModuleG\Infra\EntityManager;
use GacelaTest\Benchmark\FileCache\ModuleG\Infra\Repository;

/**
 * @extends AbstractFacade<ModuleGFactory>
 *
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 */
final class ModuleGFacade extends AbstractFacade
{
    use DocBlockResolverAwareTrait;

    public function loadGacelaCacheFile(): array
    {
        return [
            $this->getFactory()->getArrayConfigAndProvidedDependency(),
            $this->getRepository()->getAll(),
            $this->getEntityManager()->updateEntity(),
        ];
    }
}
