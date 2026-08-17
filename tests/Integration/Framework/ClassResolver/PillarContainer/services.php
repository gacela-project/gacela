<?php

declare(strict_types=1);

use GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting\GreeterInterface;
use GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting\GrumpyGreeter;

/**
 * Wiring as data, read by `loadDefinitions()` -- the file form of the same
 * source, so the pillar container is proven to apply both.
 */
return [
    GreeterInterface::class => ['singleton' => GrumpyGreeter::class],
];
