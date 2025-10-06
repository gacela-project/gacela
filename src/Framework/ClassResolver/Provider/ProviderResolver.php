<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Provider;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class ProviderResolver extends AbstractClassResolver
{
    public const TYPE = 'Provider';

    /**
     * @param object|class-string $caller
     *
     * @throws ProviderNotFoundException
     */
    public function resolve(object|string $caller): ?AbstractProvider
    {
        /** @var ?AbstractProvider $resolved */
        $resolved = $this->doResolve($caller);

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return self::TYPE;
    }
}
