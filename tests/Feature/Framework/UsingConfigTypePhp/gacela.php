<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;

return static fn (GacelaConfig $config) => $config
    ->addAppConfig('config/*.php', 'config/local.php');
