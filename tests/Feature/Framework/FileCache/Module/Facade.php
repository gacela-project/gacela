<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FileCache\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
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
