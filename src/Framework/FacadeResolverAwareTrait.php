<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Facade\FacadeResolver;

/**
 * @deprecated the new DocBlockResolverAwareTrait is capable of doing the same as FacadeResolverAwareTrait and more
 * @see DocBlockResolverAwareTrait
 */
trait FacadeResolverAwareTrait
{
    private ?AbstractFacade $facade = null;

    protected function getFacade(): AbstractFacade
    {
        if ($this->facade === null) {
            $this->facade = (new FacadeResolver())->resolve($this->facadeClass());
        }

        return $this->facade;
    }

    /**
     * @return class-string
     *
     * @deprecated using DocBlockResolverAwareTrait you don't need to define this method anymore
     * @see DocBlockResolverAwareTrait
     */
    abstract protected function facadeClass(): string;
}
