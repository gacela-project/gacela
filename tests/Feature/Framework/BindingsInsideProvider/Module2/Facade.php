<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module2;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade implements Module2FacadeInterface
{
    public function getGacelaName(): string
    {
        return $this->getFactory()
            ->createUseCase()
            ->getGacelaName();
    }
}
