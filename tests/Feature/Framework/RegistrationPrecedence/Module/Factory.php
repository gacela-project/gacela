<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\RegistrationPrecedence\Module;

use ArrayObject;
use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function getService(): ArrayObject
    {
        return $this->getProvidedDependency(Provider::SERVICE);
    }
}
