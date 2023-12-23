<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;

return static fn (GacelaConfig $config): \Gacela\Framework\Bootstrap\GacelaConfig => $config
    ->addAppConfig('config/*.php', 'config/local.php');
