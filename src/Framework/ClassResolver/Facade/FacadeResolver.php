<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Facade;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class FacadeResolver extends AbstractClassResolver
{
    /**
     * @throws FacadeNotFoundException
     */
    public function resolve(object $callerClass): AbstractFacade
    {
        /** @var ?AbstractFacade $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved === null) {
            throw new FacadeNotFoundException($callerClass);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'Facade';
    }
}
