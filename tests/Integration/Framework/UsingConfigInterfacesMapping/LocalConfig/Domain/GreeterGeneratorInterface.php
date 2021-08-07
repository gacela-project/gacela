<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain;

interface GreeterGeneratorInterface
{
    public function company(string $name): string;
}
