<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FactoryConstructorInjection\Module;

interface ClockInterface
{
    public function now(): string;
}
