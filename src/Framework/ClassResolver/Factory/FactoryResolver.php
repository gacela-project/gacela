<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Factory;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class FactoryResolver extends AbstractClassResolver
{
    public const TYPE = 'Factory';

    /**
     * @param object|class-string $caller
     */
    public function resolve(object|string $caller): AbstractFactory
    {
        /** @var AbstractFactory $resolved */
        $resolved = $this->doResolve($caller);

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return self::TYPE;
    }
}
