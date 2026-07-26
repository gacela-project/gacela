<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\Provider\ModernModule;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function getGreeting(): string
    {
        return $this->getProvidedDependency(Provider::GREETING);
    }
}
