<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigTypePhp\LocalConfig;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    public function getArrayConfig(): array
    {
        return [
            'config' => (int) $this->get('config_key'),
            'override' => (int) $this->get('override_key'),
            'local' => (int) $this->get('local_key'),
            'override_from_local' => (int) $this->get('override_key_from_local'),
        ];
    }
}
