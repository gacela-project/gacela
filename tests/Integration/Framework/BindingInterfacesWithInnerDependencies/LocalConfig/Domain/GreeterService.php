<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain;

final class GreeterService
{
    private GreeterGeneratorInterface $greeter;

    public function __construct(GreeterGeneratorInterface $greeter)
    {
        $this->greeter = $greeter;
    }

    public function generateCompanyAndName(): string
    {
        return $this->greeter->company('Gacela');
    }
}
