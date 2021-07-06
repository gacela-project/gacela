<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyProvider;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class DependencyProviderResolver extends AbstractClassResolver
{
    /**
     * @throws DependencyProviderNotFoundException
     */
    public function resolve(object $callerClass): AbstractDependencyProvider
    {
        /** @var ?AbstractDependencyProvider $resolved */
        $resolved = $this->doResolve($callerClass);

        if (null === $resolved) {
            throw new DependencyProviderNotFoundException($callerClass);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'DependencyProvider';
    }
}
