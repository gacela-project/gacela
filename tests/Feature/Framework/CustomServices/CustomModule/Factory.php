<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices\CustomModule;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Infrastructure\Persistence\Repository;

/**
 * @method Repository getRepository()
 */
final class Factory extends AbstractFactory
{
    /**
     * @return array<string,int>
     */
    public function createDummyArray(): array
    {
        return [
            'from-factory' => 1,
        ];
    }

    /**
     * @return array<string,array<string,int>>
     */
    public function findAllKeyValuesUsingRepositoryFromFactory(): array
    {
        return $this->getRepository()->findFromRepository();
    }
}
