<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Application\GreeterService;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\GreeterGeneratorInterface;

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
