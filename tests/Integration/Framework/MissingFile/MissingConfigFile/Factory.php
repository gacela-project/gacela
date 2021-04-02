<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\MissingFile\MissingConfigFile;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createDomainService(): void
    {
        $this->getConfig();
    }
}
