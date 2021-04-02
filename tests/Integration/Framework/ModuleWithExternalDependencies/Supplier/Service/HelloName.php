<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ModuleWithExternalDependencies\Supplier\Service;

use GacelaTest\Integration\Framework\ModuleWithExternalDependencies\Dependent;

final class HelloName
{
    private Dependent\FacadeInterface $dependentFacade;

    public function __construct(Dependent\FacadeInterface $dependentFacade)
    {
        $this->dependentFacade = $dependentFacade;
    }

    public function greet(string $name): array
    {
        return array_merge(
            ["Hello, $name from Supplier."],
            $this->dependentFacade->greet($name),
        );
    }
}
