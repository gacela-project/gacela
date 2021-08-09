<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingDeprecatedArrayConfig\LocalConfig\Domain;

interface GreeterGeneratorInterface
{
    public function company(string $name): string;
}
