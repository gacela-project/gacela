<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Provider;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class ProviderResolver extends AbstractClassResolver
{
    /**
     * @param object|class-string $caller
     *
     * @throws ProviderNotFoundException
     */
    public function resolve(object|string $caller): AbstractProvider
    {
        /** @var ?AbstractProvider $resolved */
        $resolved = $this->doResolve($caller);

        if ($resolved === null) {
            throw new ProviderNotFoundException($caller);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return 'Provider';
    }
}
