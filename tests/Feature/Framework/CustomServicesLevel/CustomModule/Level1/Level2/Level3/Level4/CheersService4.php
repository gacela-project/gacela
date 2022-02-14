<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule\Level1\Level2\Level3\Level4;

use Gacela\Framework\AbstractCustomService;
use GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule\Config;

/**
 * @method Config getConfig()
 */
final class CheersService4 extends AbstractCustomService
{
    public function cheers(string $name): string
    {
        $configValue = $this->getConfig()->getConfigValue();

        return "Cheers, $name! From level 4 ($configValue)";
    }
}
