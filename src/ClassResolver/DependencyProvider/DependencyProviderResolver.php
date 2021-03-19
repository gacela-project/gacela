<?php

declare(strict_types=1);

namespace Gacela\ClassResolver\DependencyProvider;

use Gacela\AbstractDependencyProvider;
use Gacela\ClassResolver\AbstractClassResolver;

final class DependencyProviderResolver extends AbstractClassResolver
{
    protected const RESOLVABLE_TYPE = 'DependencyProvider';

    /**
     * @throws DependencyProviderNotFoundException
     */
    public function resolve(object $callerClass): AbstractDependencyProvider
    {
        /** @var ?AbstractDependencyProvider $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved !== null) {
            return $resolved;
        }

        throw new DependencyProviderNotFoundException($this->getClassInfo());
    }
}
