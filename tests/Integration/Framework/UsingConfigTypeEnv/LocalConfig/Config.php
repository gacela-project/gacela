<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigTypeEnv\LocalConfig;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    public function getArrayConfig(): array
    {
        return [
            'config' => (int) $this->get('config'),
            'config_local' => (int) $this->get('config_local'),
            'override' => (int) $this->get('override'),
        ];
    }
}
