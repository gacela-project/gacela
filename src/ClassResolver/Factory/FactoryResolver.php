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
    protected const RESOLVABLE_TYPE = 'Factory';

    /**
     * @throws FactoryNotFoundException
     */
    public function resolve(object $callerClass): AbstractFactory
    {
        /** @var ?AbstractFactory $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved !== null) {
            return $resolved;
        }

        throw new FactoryNotFoundException($this->getClassInfo());
    }
}
