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
final class EntityManager extends AbstractCustomService
{
    /**
     * @return array<string,array<string,int>>
     */
    public function manageFromEntityManager(): array
    {
        return [
            'from-infrastructure-entity-manager' =>
                $this->getConfig()->getAllKeyValues()
                + $this->getFactory()->createDummyArray(),
        ];
    }
}
