<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Schema\ConfigType;

return static function (GacelaConfig $config): void {
    $config->setFileCache(false);
    $config->addAppConfig('config/*.php');
    $config->addConfigDimension('GACELA_TEST_REGION');
    $config->declareConfigSchema(['shop.currency' => ConfigType::string()->required()]);
    $config->declareDtoSchema('AcmeMerge\Order', ['id' => 'int']);
};
