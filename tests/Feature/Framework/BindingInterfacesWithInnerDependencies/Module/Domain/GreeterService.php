<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\Module\Domain;

final class GreeterService
{
    public function __construct(
        private readonly GreeterGeneratorInterface $greeter,
    ) {
    }

    public function generateCompanyAndName(): string
    {
        return $this->greeter->company('Gacela');
    }
}
