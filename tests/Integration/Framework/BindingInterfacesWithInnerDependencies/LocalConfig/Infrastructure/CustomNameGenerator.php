<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Infrastructure;

final class CustomNameGenerator
{
    public function getNames(): string
    {
        return 'Chemaclass & Jesus';
    }
}
