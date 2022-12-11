<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FileCache\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function getName(): string
    {
        return $this->getFactory()
            ->getRepository()
            ->findName();
    }
}
