<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\CustomServiceInterface;

final class Config extends AbstractConfig implements CustomServiceInterface
{
    public function getConfigValue(): string
    {
        return (string)$this->get('config-key');
    }
}
