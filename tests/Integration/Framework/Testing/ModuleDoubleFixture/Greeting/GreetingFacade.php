<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Greeting;

/**
 * The module a test wants to replace, reached the way a consumer reaches one.
 *
 * @extends \Gacela\Framework\AbstractFacade<GreetingFactory>
 */
final class GreetingFacade extends \Gacela\Framework\AbstractFacade
{
    public function greet(): string
    {
        return $this->getFactory()->createGreeter()->greet();
    }

    public function language(): string
    {
        return $this->getFactory()->language();
    }

    public function providedGreeting(): string
    {
        return $this->getFactory()->providedGreeting();
    }
}
