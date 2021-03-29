<?php

declare(strict_types=1);

namespace GacelaTest\Integration\ModuleWithoutDependencies\WithoutPrefix;

interface FacadeInterface
{
    public function greet(string $name): array;
}
