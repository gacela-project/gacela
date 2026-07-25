<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\Provider\LegacyModule;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function getGreeting(): string
    {
        return $this->getProvidedDependency(DependencyProvider::GREETING);
    }
}
