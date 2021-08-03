<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain;

interface GreeterGeneratorInterface
{
    public function greet(string $name): string;
}
