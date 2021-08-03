<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Infrastructure;

use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain\GreeterGeneratorInterface;

final class CustomCompanyGenerator implements GreeterGeneratorInterface
{
    private CustomNameGenerator $nameGenerator;

    public function __construct(CustomNameGenerator $nameGenerator)
    {
        $this->nameGenerator = $nameGenerator;
    }

    public function company(string $name): string
    {
        $names = $this->nameGenerator->getNames();

        return "Hello {$name}! Name: {$names}";
    }
}
