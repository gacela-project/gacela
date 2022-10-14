<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FileCache\Module;

use Gacela\Framework\AbstractFacade;

final class Facade extends AbstractFacade
{
    public function getName(): string
    {
        return $this->getFactory()
            ->getRepository()
            ->findName();
    }
}
