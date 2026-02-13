<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Provider;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class ProviderResolver extends AbstractClassResolver
{
    public const TYPE = 'Provider';

    /**
     * @param object|class-string $caller
     *
     * @throws ProviderNotFoundException
     *
     * @return ?AbstractProvider<AbstractConfig>
     */
    public function resolve(object|string $caller): ?AbstractProvider
    {
        /** @var ?AbstractProvider<AbstractConfig> $resolved */
        $resolved = $this->doResolve($caller);

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return self::TYPE;
    }
}
