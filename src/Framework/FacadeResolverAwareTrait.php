<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Facade\FacadeResolver;

trait FacadeResolverAwareTrait
{
    private ?AbstractFacade $facade = null;

    protected function getFacade(): AbstractFacade
    {
        if ($this->facade === null) {
            $this->facade = (new FacadeResolver())->resolve($this);
        }

        return $this->facade;
    }
}
