<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Application\GreeterService;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\GreeterGeneratorInterface;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module2\Module2FacadeInterface;

final class Factory extends AbstractFactory
{
    public function __construct(
        private readonly GreeterGeneratorInterface $greeterGenerator,
    ) {
    }

    public function createGreeterService(): GreeterService
    {
        return new GreeterService(
            $this->greeterGenerator,
            $this->getModule2Facade(),
        );
    }

    private function getModule2Facade(): Module2FacadeInterface
    {
        return $this->getProvidedDependency(Module2FacadeInterface::class);
    }
}
