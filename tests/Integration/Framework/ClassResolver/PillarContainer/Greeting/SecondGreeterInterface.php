<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\PillarContainer\Greeting;

/**
 * A second contract so a test can register one id imperatively and another as
 * data, and tell the two announcements apart.
 */
interface SecondGreeterInterface
{
    public function greet(): string;
}
