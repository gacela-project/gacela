<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\BootstrapReadsGacelaPhpOnce\Module\CountingPlugin;

return static function (GacelaConfig $config): void {
    $config->setFileCache(false);
    $config->addAppConfig('config/*.php');
    $config->addPlugin(CountingPlugin::class);
};
