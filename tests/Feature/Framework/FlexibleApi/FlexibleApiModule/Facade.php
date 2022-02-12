<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FlexibleApi\FlexibleApiModule;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Framework\FlexibleApi\FlexibleApiModule\Infrastructure\Repository;

/**
 * @method Repository getRepository()
 */
final class Facade extends AbstractFacade
{
    /**
     * @return array<string,int>
     */
    public function findAllKeyValuesUsingRepository(): array
    {
        return $this->getRepository()->findAllKeyValues();
    }
}
