<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\Factory;

use Gacela\AbstractConfig;
use Gacela\AbstractFactory;
use Gacela\ClassResolver\AbstractClassResolver;

/**
 * @method AbstractConfig getResolvedClassInstance()
 */
final class FactoryResolver extends AbstractClassResolver
{
    /**
     * @throws FactoryNotFoundException
     */
    public function resolve(object $callerClass): AbstractFactory
    {
        /** @var ?AbstractFactory $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved === null) {
            throw new FactoryNotFoundException($this->getClassInfo());
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'Factory';
    }
}
