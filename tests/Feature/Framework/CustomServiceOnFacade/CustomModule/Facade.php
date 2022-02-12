<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceOnFacade\CustomModule;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Framework\CustomServiceOnFacade\CustomModule\Infrastructure\EntityManager;
use GacelaTest\Feature\Framework\CustomServiceOnFacade\CustomModule\Infrastructure\Repository;

/**
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 */
final class Facade extends AbstractFacade
{
    /**
     * @return array<string,array<string,int>>
     */
    public function findAllKeyValuesUsingRepository(): array
    {
        return $this->getRepository()->findFromRepository()
            + $this->getEntityManager()->manageFromEntityManager();
    }
}
