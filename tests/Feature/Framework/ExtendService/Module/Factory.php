<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendService\Module;

use ArrayObject;
use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function getArrayAsObject(): ArrayObject
    {
        return $this->getProvidedDependency(DependencyProvider::ARRAY_AS_OBJECT);
    }

    public function getArrayFromFunction(): ArrayObject
    {
        return $this->getProvidedDependency(DependencyProvider::ARRAY_FROM_FUNCTION);
    }
}
