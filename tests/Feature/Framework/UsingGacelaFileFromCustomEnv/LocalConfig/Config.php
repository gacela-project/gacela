<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingGacelaFileFromCustomEnv\LocalConfig;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    public function getArrayConfig(): array
    {
        return [
            'default_key' => (string)$this->get('default_key'),
            'key' => (string)$this->get('key'),
        ];
    }
}
