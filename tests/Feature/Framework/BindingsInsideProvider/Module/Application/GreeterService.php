<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Application;

use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\GreeterGeneratorInterface;

final class GreeterService
{
    public function __construct(
        private readonly GreeterGeneratorInterface $greeter,
    ) {
    }

    public function generateCompanyAndName(): string
    {
        return $this->greeter->company('Gacela');
    }
}
