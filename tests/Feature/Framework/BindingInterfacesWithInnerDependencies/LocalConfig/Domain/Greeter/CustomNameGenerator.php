<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\Greeter;

final class CustomNameGenerator
{
    public function getNames(): string
    {
        return 'Chemaclass & Jesus';
    }
}
