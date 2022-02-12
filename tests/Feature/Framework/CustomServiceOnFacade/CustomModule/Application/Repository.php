<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceOnFacade\CustomModule\Application;

use Gacela\Framework\AbstractCustomService;
use GacelaTest\Feature\Framework\CustomServiceOnFacade\CustomModule\Config;

/**
 * @method Factory getFactory()
 * @method Config getConfig()
 */
final class Repository extends AbstractCustomService
{
    /**
     * @return array<string,array<string,int>>
     */
    public function findFromRepository(): array
    {
        return [
            'from-application-repository' =>
                $this->getConfig()->getAllKeyValues()
                + $this->getFactory()->createDummyArray(),
        ];
    }
}
