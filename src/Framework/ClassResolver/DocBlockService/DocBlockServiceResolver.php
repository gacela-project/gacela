<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class DocBlockServiceResolver extends AbstractClassResolver
{
    private string $resolvableType;

    public function __construct(string $resolvableType)
    {
        $this->resolvableType = $resolvableType;
    }

    /**
     * @param object|class-string $caller
     *
     * @throws DocBlockServiceNotFoundException
     */
    public function resolve($caller): ?object
    {
        $resolved = $this->doResolve($caller);

        if ($resolved === null) {
            throw new DocBlockServiceNotFoundException($caller, $this->resolvableType);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return $this->resolvableType;
    }
}
