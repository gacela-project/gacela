<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Infrastructure;

use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\GreeterGeneratorInterface;

final class IncorrectCompanyGenerator implements GreeterGeneratorInterface
{
    public function company(string $name): string
    {
        throw new \RuntimeException('Test should fail!');
    }
}
