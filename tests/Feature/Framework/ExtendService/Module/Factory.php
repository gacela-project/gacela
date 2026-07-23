<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendService\Module;

use ArrayObject;
use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function getArrayAsObject(): ArrayObject
    {
        return $this->getProvidedDependency(Provider::ARRAY_AS_OBJECT);
    }

    public function getArrayFromFunction(): ArrayObject
    {
        return $this->getProvidedDependency(Provider::ARRAY_FROM_FUNCTION);
    }
}
