<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\Module\Domain;

interface GreeterGeneratorInterface
{
    public function company(string $name): string;
}
