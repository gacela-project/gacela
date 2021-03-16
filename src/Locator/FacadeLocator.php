<?php

declare(strict_types=1);

namespace Gacela\Locator;

use Gacela\ClassResolver\Facade\FacadeResolver;

final class FacadeLocator implements LocatorInterface
{
    public function locate(string $bundle)
    {
        return $this->getFacadeResolver()->resolve($bundle);
    }

    private function getFacadeResolver(): FacadeResolver
    {
        return new FacadeResolver();
    }
}
