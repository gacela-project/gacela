<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;

return static fn () => new class() extends AbstractConfigGacela {
    public function config(ConfigBuilder $configBuilder): void
    {
        $configBuilder->add('config/*.php', 'config/local.php');
    }
};
