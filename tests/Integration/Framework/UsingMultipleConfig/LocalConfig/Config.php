<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingMultipleConfig\LocalConfig;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    public function getArrayConfig(): array
    {
        return [
            'config-env' => (int) $this->get('config-env'),
            'config-php' => (int) $this->get('config-php'),
            'override' => (int) $this->get('override'),
        ];
    }
}
