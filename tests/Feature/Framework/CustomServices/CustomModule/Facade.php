<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices\CustomModule;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Application\Repository;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Infrastructure\EntityManager;

/**
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 */
final class Facade extends AbstractFacade
{
    /**
     * @return array<string,array<string,int>>
     */
    public function usingCustomServicesFromFacade(): array
    {
        return $this->getRepository()->findFromRepository()
            + $this->getEntityManager()->manageFromEntityManager();
    }

    /**
     * @return array<string,array<string,int>>
     */
    public function usingCustomServicesFromFactory(): array
    {
        return $this->getFactory()->findAllKeyValuesUsingRepositoryFromFactory();
    }
}
