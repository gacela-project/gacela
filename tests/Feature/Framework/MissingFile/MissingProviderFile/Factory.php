<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile\MissingProviderFile;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createDomainService(): void
    {
        $this->getProvidedDependency('non-existing-service');
    }
}
