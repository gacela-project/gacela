<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule\Level1\Level2\Level3\Level4;

use Gacela\Framework\AbstractCustomService;
use GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule\Config;

/**
 * @method Config getConfig()
 */
final class HappyService4 extends AbstractCustomService
{
    public function greet(string $name): string
    {
        $configValue = $this->getConfig()->getConfigValue();

        return "Hi, $name! From level 4 ($configValue)";
    }
}
