<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Supplier\Service;

use GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Dependent;

use function sprintf;

final class HelloName
{
    private Dependent\FacadeInterface $dependentFacade;

    public function __construct(Dependent\FacadeInterface $dependentFacade)
    {
        $this->dependentFacade = $dependentFacade;
    }

    public function greet(string $name): array
    {
        return [
            sprintf('Hello, %s from Supplier.', $name),
            ...$this->dependentFacade->greet($name),
        ];
    }
}
