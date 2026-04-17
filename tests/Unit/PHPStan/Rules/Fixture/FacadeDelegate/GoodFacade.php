<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeDelegate;

use Gacela\Framework\AbstractFacade;

/**
 * @method \GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeDelegate\FacadeFactoryStub getFactory()
 */
final class GoodFacade extends AbstractFacade
{
    public function delegateReturn(): string
    {
        return $this->getFactory()->createService()->run();
    }

    public function delegateExpression(): void
    {
        $this->getFactory()->createService()->run();
    }

    public function configDelegate(): string
    {
        return $this->getConfig()->getEndpoint();
    }

    public function providerDelegate(): object
    {
        return $this->getProvider()->getClient();
    }

    public function propertyAccess(): mixed
    {
        return $this->getFactory()->createThing()->value;
    }

    public function nullsafeChain(): mixed
    {
        return $this->getFactory()?->createThing()?->value;
    }

    public function cachedArrow(): mixed
    {
        return $this->cached(fn () => $this->getFactory()->createRepository()->fetchData());
    }

    public function cachedClosure(): mixed
    {
        return $this->cached(fn (): mixed => $this->getFactory()->createRepository()->fetchData());
    }

    public function cachedConfig(): string
    {
        return $this->cached(fn (): string => $this->getConfig()->getEndpoint());
    }
}
