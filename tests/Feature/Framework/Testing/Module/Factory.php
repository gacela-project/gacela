<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\Testing\Module;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

final class Factory extends AbstractFactory
{
    public function createGreeting(): string
    {
        return (string)$this->getProvidedDependency(Provider::GREETING);
    }

    /**
     * The dispatcher as a provided dependency, which is how an application
     * dispatches its own events.
     */
    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->getProvidedDependency(EventDispatcherInterface::class);
    }
}
