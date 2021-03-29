<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleWithExternalDependencies\Supplier\Service;

use GacelaTest\Integration\ModuleWithExternalDependencies\Dependent\Facade;

final class HelloName
{
    private Facade $exampleAFacade;

    public function __construct(Facade $exampleAFacade)
    {
        $this->exampleAFacade = $exampleAFacade;
    }

    public function greet(string $name): array
    {
        return array_merge(
            ["Hello, $name from Supplier."],
            $this->exampleAFacade->greet($name),
        );
    }
}
