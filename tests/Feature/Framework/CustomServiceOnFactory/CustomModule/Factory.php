<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceOnFactory\CustomModule;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\CustomServiceOnFactory\CustomModule\Infrastructure\Repository;

/**
 * @method Repository getRepository()
 */
final class Factory extends AbstractFactory
{
    /**
     * @return array<string,int>
     */
    public function findAllKeyValuesUsingRepositoryFromFactory(): array
    {
        return $this->getRepository()->findAllKeyValues();
    }
}
