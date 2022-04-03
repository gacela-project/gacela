<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyProvider;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class DependencyProviderResolver extends AbstractClassResolver
{
    /**
     * @param object|class-string $caller
     *
     * @throws DependencyProviderNotFoundException
     */
    public function resolve($caller): AbstractDependencyProvider
    {
        /** @var ?AbstractDependencyProvider $resolved */
        $resolved = $this->doResolve($caller);

        if ($resolved === null) {
            throw new DependencyProviderNotFoundException($caller);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'DependencyProvider';
    }
}
