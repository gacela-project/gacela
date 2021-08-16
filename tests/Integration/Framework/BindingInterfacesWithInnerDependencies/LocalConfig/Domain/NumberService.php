<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain;

final class NumberService
{
    private GreeterGeneratorInterface $numberGenerator;

    public function __construct(GreeterGeneratorInterface $numberGenerator)
    {
        $this->numberGenerator = $numberGenerator;
    }

    public function generateCompanyAndName(): string
    {
        return $this->numberGenerator->company('Gacela');
    }
}
