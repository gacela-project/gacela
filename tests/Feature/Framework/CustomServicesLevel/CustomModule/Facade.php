<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule\Level1\Level2\Level3\Level4\HappyService4;

/**
 * @method Config getConfig()
 * @method HappyService4 happyService4()
 */
final class Facade extends AbstractFacade
{
    /**
     * @return array<string,int>
     */
    public function getAllKeyValuesFromConfig(): array
    {
        return $this->getConfig()->getAllKeyValues();
    }

    public function greet(string $name): string
    {
        return $this->happyService4()->greet($name);
    }
}
