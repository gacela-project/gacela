<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Facade\FacadeResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;

final class DocBlockServiceResolver extends AbstractClassResolver
{
    public function __construct(
        private readonly string $resolvableType,
    ) {
    }

    /**
     * @param object|class-string $caller
     *
     * @throws DocBlockServiceNotFoundException
     */
    public function resolve(object|string $caller): object
    {
        $resolved = $this->doResolve($caller);

        if ($resolved === null) {
            throw new DocBlockServiceNotFoundException($caller, $this->resolvableType);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return $this->resolvableType;
    }

    /**
     * Unlike the fixed resolvers, this resolver's type is set dynamically at
     * construction, so the default must be keyed on that value rather than
     * on the resolver's own class identity.
     */
    protected function createDefaultGacelaClass(): ?object
    {
        return match ($this->resolvableType) {
            FacadeResolver::TYPE => new /**
             * @extends AbstractFacade<AbstractFactory>
             */ class() extends AbstractFacade {},
            FactoryResolver::TYPE => new /**
             * @extends AbstractFactory<AbstractConfig>
             */ class() extends AbstractFactory {},
            ConfigResolver::TYPE => new class() extends AbstractConfig {},
            default => null,
        };
    }
}
