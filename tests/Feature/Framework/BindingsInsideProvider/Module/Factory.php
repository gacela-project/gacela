<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\GreeterGeneratorInterface;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\GreeterService;

final class Factory extends AbstractFactory
{
    public function __construct(
        private readonly GreeterGeneratorInterface $greeterGenerator,
    ) {
    }

    public function createGreeterService(): GreeterService
    {
        return new GreeterService($this->greeterGenerator);
    }
}
