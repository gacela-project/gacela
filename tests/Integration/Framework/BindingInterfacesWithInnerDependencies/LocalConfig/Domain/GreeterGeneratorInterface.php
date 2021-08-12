<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain;

interface GreeterGeneratorInterface
{
    public function company(string $name): string;
}
