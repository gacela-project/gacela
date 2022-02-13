<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\CustomService;

use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\CustomServiceInterface;

final class CustomServiceResolver extends AbstractClassResolver
{
    private string $resolvableType;

    public function __construct(string $resolvableType)
    {
        $this->resolvableType = $resolvableType;
    }

    /**
     * @throws CustomServiceNotFoundException
     * @throws CustomServiceNotValidException
     */
    public function resolve(object $callerClass): CustomServiceInterface
    {
        /** @var ?CustomServiceInterface $resolved */
        $resolved = $this->doResolve($callerClass);

        if (null === $resolved) {
            throw new CustomServiceNotFoundException($callerClass, $this->resolvableType);
        }

        if (!$resolved instanceof CustomServiceInterface) {
            throw new CustomServiceNotValidException($callerClass, $this->resolvableType);
        }

        return $resolved;
    }

    protected function getResolvableType(): string
    {
        return $this->resolvableType;
    }
}
