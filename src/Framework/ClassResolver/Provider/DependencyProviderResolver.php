<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Provider;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

/**
 * @psalm-suppress DeprecatedClass
 */
final class DependencyProviderResolver extends AbstractClassResolver
{
    public const TYPE = 'DependencyProvider';

    /**
     * @param object|class-string $caller
     */
    public function resolve(object|string $caller): ?AbstractProvider
    {
        /** @var ?AbstractDependencyProvider $resolved */
        $resolved = $this->doResolve($caller);

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return self::TYPE;
    }
}
