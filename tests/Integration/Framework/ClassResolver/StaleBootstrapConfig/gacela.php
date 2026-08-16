<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Integration\Framework\ClassResolver\StaleBootstrapConfig\Greeting\EnglishGreeter;
use GacelaTest\Integration\Framework\ClassResolver\StaleBootstrapConfig\Greeting\GreeterInterface;

return static function (GacelaConfig $config): void {
    $config->addBinding(GreeterInterface::class, EnglishGreeter::class);
};
