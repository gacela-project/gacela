<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Facade;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class FacadeResolver extends AbstractClassResolver
{
    /**
     * @param object|class-string $caller
     *
     * @throws FacadeNotFoundException
     */
    public function resolve(object|string $caller): AbstractFacade
    {
        /** @var ?AbstractFacade $resolved */
        $resolved = $this->doResolve($caller);

        if ($resolved === null) {
            throw new FacadeNotFoundException($caller);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'Facade';
    }
}
