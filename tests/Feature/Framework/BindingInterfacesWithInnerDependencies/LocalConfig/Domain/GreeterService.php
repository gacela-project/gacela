<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain;

final class GreeterService
{
    public function __construct(
        private GreeterGeneratorInterface $greeter,
    ) {
    }

    public function generateCompanyAndName(): string
    {
        return $this->greeter->company('Gacela');
    }
}
