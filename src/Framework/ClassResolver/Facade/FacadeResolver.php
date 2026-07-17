<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Facade;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class FacadeResolver extends AbstractClassResolver
{
    public const TYPE = 'Facade';

    /**
     * @param object|class-string $caller
     */
    public function resolve(object|string $caller): AbstractFacade
    {
        /** @var AbstractFacade $resolved */
        $resolved = $this->doResolve($caller);

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return self::TYPE;
    }

    protected function createDefaultGacelaClass(): AbstractFacade
    {
        return new /**
         * @extends AbstractFacade<AbstractFactory>
         */ class() extends AbstractFacade {};
    }
}
