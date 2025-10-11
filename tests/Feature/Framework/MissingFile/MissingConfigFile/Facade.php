<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile\MissingConfigFile;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function getConfig(): AbstractConfig
    {
        return $this->getFactory()->getConfig();
    }
}
