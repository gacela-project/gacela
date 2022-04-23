<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\CustomService;

use Gacela\Framework\AbstractCustomService;
use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class CustomServiceResolver extends AbstractClassResolver
{
    private string $resolvableType;

    public function __construct(string $resolvableType)
    {
        $this->resolvableType = $resolvableType;
    }

    /**
     * @param object|class-string $caller
     *
     * @throws CustomServiceNotFoundException
     */
    public function resolve($caller): AbstractCustomService
    {
        /** @var ?AbstractCustomService $resolved */
        $resolved = $this->doResolve($caller);

        if ($resolved === null) {
            throw new CustomServiceNotFoundException($caller, $this->resolvableType);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return $this->resolvableType;
    }
}
