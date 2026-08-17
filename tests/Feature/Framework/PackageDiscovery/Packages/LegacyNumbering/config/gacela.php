<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;

return static function (GacelaConfig $config): void {
    $config->addAppConfigKeyValue('legacy.numbering', 'roman');
};
