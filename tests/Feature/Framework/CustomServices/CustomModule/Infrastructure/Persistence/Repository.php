<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices\CustomModule\Infrastructure\Persistence;

use Gacela\Framework\AbstractCustomService;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Config;
use GacelaTest\Feature\Framework\CustomServices\CustomModule\Infrastructure\Factory;

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
            'from-infrastructure-repository' =>
                $this->getConfig()->getAllKeyValues()
                + $this->getFactory()->createDummyArray(),
        ];
    }
}
