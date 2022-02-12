<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain;

interface GreeterGeneratorInterface
{
    public function company(string $name): string;
}
