<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ModuleWithExternalDependencies\Supplier;

interface FacadeInterface
{
    public function greet(string $name): array;
}
