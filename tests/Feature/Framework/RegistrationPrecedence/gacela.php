<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    /** @var Closure(GacelaConfig):void $registerInFile */
    $registerInFile = $config->getExternalService('registerInFile');
    $registerInFile($config);
};
