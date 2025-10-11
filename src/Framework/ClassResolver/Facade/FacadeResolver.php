<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Facade;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Override;

final class FacadeResolver extends AbstractClassResolver
{
    public const TYPE = 'Facade';

    /**
     * @param object|class-string $caller
     */
    #[Override]
    public function resolve(object|string $caller): AbstractFacade
    {
        /** @var AbstractFacade $resolved */
        $resolved = $this->doResolve($caller);

        return $resolved;
    }

    #[Override]
    protected function getResolvableType(): string
    {
        return self::TYPE;
    }
}
