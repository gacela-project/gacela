<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule\Level1\Level2\Level3\Level4\CheersService4;

/**
 * @method CheersService4 cheersService4()
 */
final class Facade extends AbstractFacade
{
    public function cheers(string $name): string
    {
        return $this->cheersService4()->cheers($name);
    }
}
