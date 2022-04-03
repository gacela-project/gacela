<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Factory;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class FactoryResolver extends AbstractClassResolver
{
    /**
     * @param object|class-string $caller
     *
     * @throws FactoryNotFoundException
     */
    public function resolve($caller): AbstractFactory
    {
        /** @var ?AbstractFactory $resolved */
        $resolved = $this->doResolve($caller);

        if ($resolved === null) {
            throw new FactoryNotFoundException($caller);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'Factory';
    }
}
