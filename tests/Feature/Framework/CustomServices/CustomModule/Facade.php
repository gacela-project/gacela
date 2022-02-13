<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices\CustomModule;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Application\Greeter;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Application\Repository;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Infrastructure\Persistence\EntityManager;

/**
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 * @method Greeter getGreeter()
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

    public function greetUsingPlainCustomService(string $name): string
    {
        return $this->getGreeter()->greet($name);
    }
}
