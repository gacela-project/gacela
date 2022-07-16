<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\AddAppConfigKeyValuesInGacelaBootstrap\Module;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    public function getData(): array
    {
        return [
            'first_key' => $this->get('first_key'),
            'some_key' => $this->get('some_key'),
            'another_key' => $this->get('another_key'),
            'override_key' => $this->get('override_key'),
        ];
    }
}
