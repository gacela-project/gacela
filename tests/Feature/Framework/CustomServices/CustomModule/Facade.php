<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices\CustomModule;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Application\InvalidGreeter;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Application\Repository;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Application\ValidGreeter;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Infrastructure\Persistence\EntityManager;

/**
 * @method Factory getFactory()
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 * @method InvalidGreeter getInvalidGreeter()
 * @method ValidGreeter getValidGreeter()
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

    /**
     * When `getInvalidGreeter()` is trying to be resolved as `Application/InvalidGreeter`, it will throw an
     * CustomServiceNotValidException because it's not implementing `CustomServiceInterface`.
     */
    public function greetUsingInvalidCustomService(string $name): string
    {
        return $this->getInvalidGreeter()->greet($name);
    }

    /**
     * The `getValidGreeter()` will be resolved successfully as `Application/ValidGreeter` because it's
     * implementing `CustomServiceInterface`.
     */
    public function greetUsingValidCustomService(string $name): string
    {
        return $this->getValidGreeter()->greet($name);
    }
}
