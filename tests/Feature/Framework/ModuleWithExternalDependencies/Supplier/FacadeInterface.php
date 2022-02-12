<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Supplier;

interface FacadeInterface
{
    public function greet(string $name): array;
}
