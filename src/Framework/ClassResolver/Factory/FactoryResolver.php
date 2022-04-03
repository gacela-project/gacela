<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Factory;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class FactoryResolver extends AbstractClassResolver
{
    /**
     * @param object|class-string $callerClass
     *
     * @throws FactoryNotFoundException
     */
    public function resolve($callerClass): AbstractFactory
    {
        /** @var ?AbstractFactory $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved === null) {
            throw new FactoryNotFoundException($callerClass);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'Factory';
    }
}
