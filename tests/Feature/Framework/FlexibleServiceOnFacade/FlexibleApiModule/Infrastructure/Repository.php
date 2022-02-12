<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FlexibleServiceOnFacade\FlexibleApiModule\Infrastructure;

use Gacela\Framework\AbstractFlexibleService;
use GacelaTest\Feature\Framework\FlexibleServiceOnFacade\FlexibleApiModule\Config;

/**
 * @method Factory getFactory()
 * @method Config getConfig()
 */
final class Repository extends AbstractFlexibleService
{
    /**
     * @return array<string,int>
     */
    public function findAllKeyValues(): array
    {
        return $this->getConfig()->getAllKeyValues()
            + $this->getFactory()->createDummyArray();
    }
}
