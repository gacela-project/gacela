<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingCustomGacelaFileSuffix\LocalConfig;

use Gacela\Framework\AbstractConfig;

final class ConfCustom extends AbstractConfig
{
    public function getConfigValue(): string
    {
        return $this->get('config-key');
    }
}
