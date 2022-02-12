<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile\MissingContainerServiceKey;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createDomainService(): void
    {
        $this->getProvidedDependency('non-existing-service');
    }
}
