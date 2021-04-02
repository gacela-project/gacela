<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ModuleWithoutDependencies\WithPrefix;

interface WithPrefixFacadeInterface
{
    public function greet(string $name): array;
}
