<?php

declare(strict_types=1);

namespace GacelaTest\Integration\MissingFile\MissingConfigFile;

use Gacela\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createDomainService(): void
    {
        $this->getConfig();
    }
}
