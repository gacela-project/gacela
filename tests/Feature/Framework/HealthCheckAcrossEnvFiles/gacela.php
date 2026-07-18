<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\HealthCheckAcrossEnvFiles\HealthCheckA;

return static function (GacelaConfig $config): void {
    $config->addHealthCheck(HealthCheckA::class);
};
