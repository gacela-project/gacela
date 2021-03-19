<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Facade;

use Gacela\AbstractFacade;
use Gacela\ClassResolver\AbstractClassResolver;

final class FacadeResolver extends AbstractClassResolver
{
    protected const RESOLVABLE_TYPE = 'Facade';

    /**
     * @throws FacadeNotFoundException
     */
    public function resolve(object $callerClass): AbstractFacade
    {
        /** @var ?AbstractFacade $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved !== null) {
            return $resolved;
        }

        throw new FacadeNotFoundException($this->getClassInfo());
    }
}
