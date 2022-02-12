<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceOnFactory\CustomModule\Infrastructure;

use Gacela\Framework\AbstractCustomService;
use GacelaTest\Feature\Framework\CustomServiceOnFactory\CustomModule\Config;

/**
 * @method Factory getFactory()
 * @method Config getConfig()
 */
final class Repository extends AbstractCustomService
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
