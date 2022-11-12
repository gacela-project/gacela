<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerFactory\Module;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\StringValue;

final class Factory extends AbstractFactory
{
    public function getCachedSeed(): string
    {
        return $this->getProvidedDependency(DependencyProvider::CACHED_SEED);
    }

    public function getRandomSeed(): string
    {
        return $this->getProvidedDependency(DependencyProvider::RANDOM_SEED);
    }

    public function getRandomStringValue(): StringValue
    {
        return $this->getProvidedDependency(DependencyProvider::RANDOM_STRING_VALUE);
    }
}
