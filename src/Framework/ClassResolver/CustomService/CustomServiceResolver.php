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
     * @throws CustomServiceNotFoundException
     */
    public function resolve(object $callerClass): AbstractCustomService
    {
        /** @var ?AbstractCustomService $resolved */
        $resolved = $this->doResolve($callerClass);

        if (null === $resolved) {
            throw new CustomServiceNotFoundException($callerClass);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return $this->resolvableType;
    }
}
