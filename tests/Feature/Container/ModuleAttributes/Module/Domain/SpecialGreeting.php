<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Container\ModuleAttributes\Module\Domain;

final class SpecialGreeting implements GreetingInterface
{
    public function greet(): string
    {
        return 'hello from the #[Inject] override';
    }
}
