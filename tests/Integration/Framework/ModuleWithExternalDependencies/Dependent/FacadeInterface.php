<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ModuleWithExternalDependencies\Dependent;

interface FacadeInterface
{
    public function greet(string $name): array;
}
