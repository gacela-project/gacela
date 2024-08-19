<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Application;

use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\GreeterGeneratorInterface;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module2\Module2FacadeInterface;

final class GreeterService
{
    public function __construct(
        private readonly GreeterGeneratorInterface $greeter,
        private readonly Module2FacadeInterface $module2Facade,
    ) {
    }

    public function generateCompanyAndName(): string
    {
        return $this->greeter->company(
            $this->module2Facade->getGacelaName(),
        );
    }
}
