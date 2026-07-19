<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Container\ModuleAttributes\Module\Domain;

final class PlainGreeter
{
    public function __construct(
        public readonly GreetingInterface $greeting,
    ) {
    }
}
