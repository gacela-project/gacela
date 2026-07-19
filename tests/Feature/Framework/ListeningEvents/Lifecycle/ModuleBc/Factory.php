<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\Lifecycle\ModuleBc;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createGreeting(): string
    {
        return (string)$this->getProvidedDependency(DependencyProvider::GREETING);
    }
}
