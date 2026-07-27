<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerTags\Checkout;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    /**
     * @return list<string>
     */
    public function validatorNames(): array
    {
        return $this->getFactory()->createValidatorNames();
    }
}
