<?php

declare(strict_types=1);

namespace GacelaTest\Integration\MissingFile\MissingRepositoryFile;

use Gacela\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createDomainService(): void
    {
        $this->getRepository();
    }
}
