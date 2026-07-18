<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\HealthCheckAcrossEnvFiles\HealthCheckB;

return static function (GacelaConfig $config): void {
    $config->addHealthCheck(HealthCheckB::class);
};
