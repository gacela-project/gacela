<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DependencyProvider;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class DependencyProviderResolver extends AbstractClassResolver
{
    /**
     * @param object|class-string $callerClass
     *
     * @throws DependencyProviderNotFoundException
     */
    public function resolve($callerClass): AbstractDependencyProvider
    {
        /** @var ?AbstractDependencyProvider $resolved */
        $resolved = $this->doResolve($callerClass);

        if ($resolved === null) {
            throw new DependencyProviderNotFoundException($callerClass);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'DependencyProvider';
    }
}
