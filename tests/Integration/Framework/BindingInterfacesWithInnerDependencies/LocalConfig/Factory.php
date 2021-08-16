<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\NumberService;

final class Factory extends AbstractFactory
{
    private GreeterGeneratorInterface $companyGenerator;

    public function __construct(GreeterGeneratorInterface $companyGenerator)
    {
        $this->companyGenerator = $companyGenerator;
    }

    public function createCompanyService(): NumberService
    {
        return new NumberService($this->companyGenerator);
    }
}
