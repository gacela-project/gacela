<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\GreeterService;

final class Factory extends AbstractFactory
{
    private GreeterGeneratorInterface $greeterGenerator;

    public function __construct(GreeterGeneratorInterface $greeterGenerator)
    {
        $this->greeterGenerator = $greeterGenerator;
    }

    public function createGreeterService(): GreeterService
    {
        return new GreeterService($this->greeterGenerator);
    }
}
