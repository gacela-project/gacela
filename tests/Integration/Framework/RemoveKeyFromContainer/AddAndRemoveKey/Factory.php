<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RemoveKeyFromContainer\AddAndRemoveKey;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createDomainService(): void
    {
        $this->getProvidedDependency(DependencyProvider::FACADE_NAME);
    }
}
