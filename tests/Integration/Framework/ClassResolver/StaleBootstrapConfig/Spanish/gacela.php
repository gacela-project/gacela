<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Integration\Framework\ClassResolver\StaleBootstrapConfig\Greeting\GreeterInterface;
use GacelaTest\Integration\Framework\ClassResolver\StaleBootstrapConfig\Greeting\SpanishGreeter;

/**
 * The second application: the same interface, bound to the other implementation.
 */
return static function (GacelaConfig $config): void {
    $config->addBinding(GreeterInterface::class, SpanishGreeter::class);
};
