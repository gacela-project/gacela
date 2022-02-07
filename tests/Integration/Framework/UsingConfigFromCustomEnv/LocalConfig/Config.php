<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigFromCustomEnv\LocalConfig;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    public function getArrayConfig(): array
    {
        return [
            'config-php' => (int) $this->get('config-php'),
            'override' => (int) $this->get('override'),
        ];
    }
}
