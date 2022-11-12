<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerFactory\Module;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\StringValue;

final class Factory extends AbstractFactory
{
    public function createRandomStringValue(): StringValue
    {
        return $this->getProvidedDependency(DependencyProvider::RANDOM_STRING_VALUE);
    }
}
