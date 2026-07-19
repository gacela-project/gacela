<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Container\ModuleAttributes\Module\Domain;

use Gacela\Container\Attribute\Inject;

final class GreeterWithInject
{
    public function __construct(
        #[Inject(SpecialGreeting::class)]
        public readonly GreetingInterface $greeting,
    ) {
    }
}
