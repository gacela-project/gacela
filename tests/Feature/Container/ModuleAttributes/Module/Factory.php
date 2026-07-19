<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Container\ModuleAttributes\Module;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Container\ModuleAttributes\Module\Domain\CounterServiceSingleton;
use GacelaTest\Feature\Container\ModuleAttributes\Module\Domain\FreshPrinter;
use GacelaTest\Feature\Container\ModuleAttributes\Module\Domain\GreeterWithInject;
use GacelaTest\Feature\Container\ModuleAttributes\Module\Domain\PlainGreeter;

final class Factory extends AbstractFactory
{
    public function createSingletonCounter(): CounterServiceSingleton
    {
        /** @var CounterServiceSingleton */
        return $this->getProvidedDependency(CounterServiceSingleton::class);
    }

    public function createFreshPrinter(): FreshPrinter
    {
        /** @var FreshPrinter */
        return $this->getProvidedDependency(FreshPrinter::class);
    }

    public function createGreeterWithInject(): GreeterWithInject
    {
        /** @var GreeterWithInject */
        return $this->getProvidedDependency(GreeterWithInject::class);
    }

    public function createPlainGreeter(): PlainGreeter
    {
        /** @var PlainGreeter */
        return $this->getProvidedDependency(PlainGreeter::class);
    }
}
