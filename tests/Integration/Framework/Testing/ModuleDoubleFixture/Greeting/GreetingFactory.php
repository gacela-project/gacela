<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting\Domain\Greeter;

/**
 * Not final on purpose: a fixture exists to be replaced, and one of the ways a
 * test replaces it is with a PHPUnit mock, which cannot extend a final class.
 *
 * @extends AbstractFactory<GreetingConfig>
 */
class GreetingFactory extends AbstractFactory
{
    public function createGreeter(): Greeter
    {
        return new Greeter('hello');
    }

    public function language(): string
    {
        return $this->getConfig()->language();
    }

    public function providedGreeting(): string
    {
        return (string)$this->getProvidedDependency(GreetingProvider::GREETING);
    }
}
