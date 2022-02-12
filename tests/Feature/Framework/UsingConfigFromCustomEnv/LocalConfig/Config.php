<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingConfigFromCustomEnv\LocalConfig;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    public function getArrayConfig(): array
    {
        return [
            'from-default' => (int)$this->get('from-default'),
            'from-default-env-override' => (int)$this->get('from-default-env-override'),
            'from-local-override' => (int)$this->get('from-local-override'),
        ];
    }
}
