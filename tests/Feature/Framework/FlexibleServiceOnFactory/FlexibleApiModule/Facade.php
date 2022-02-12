<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FlexibleServiceOnFactory\FlexibleApiModule;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    /**
     * @return array<string,int>
     */
    public function findAllKeyValuesUsingRepository(): array
    {
        return $this->getFactory()->findAllKeyValuesUsingRepositoryFromFactory();
    }
}
