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

        if ($resolved === null) {
            throw new DependencyProviderNotFoundException($this->getClassInfo());
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'DependencyProvider';
    }
}