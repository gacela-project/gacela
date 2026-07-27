<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture\StatefulFixture\Greeting;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Attribute\Cacheable;

/**
 * @extends AbstractFacade<GreetingFactory>
 */
final class GreetingFacade extends AbstractFacade
{
    public function greet(string $name): string
    {
        return $this->getFactory()
            ->createGreeter()
            ->greet($name);
    }

    /**
     * Reached through CacheableTrait, so resolving this module also populates
     * the #[Cacheable] attribute cache and the storage behind it.
     */
    #[Cacheable]
    public function cachedGreet(string $name): string
    {
        return $this->cached(fn (): string => $this->greet($name));
    }
}
