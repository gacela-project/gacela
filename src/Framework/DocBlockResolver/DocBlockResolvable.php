<?php

declare(strict_types=1);

namespace Gacela\Framework\DocBlockResolver;

final class DocBlockResolvable
{
    /** @var class-string */
    private string $className;

    private string $resolvableType;

    /**
     * @param class-string $className
     */
    public function __construct(string $className, string $resolvableType)
    {
        /** @psalm-suppress PropertyTypeCoercion */
        $this->className = '\\' . ltrim($className, '\\'); // @phpstan-ignore-line
        $this->resolvableType = $resolvableType;
    }

    /**
     * @return class-string
     */
    public function className(): string
    {
        return $this->className;
    }

    public function resolvableType(): string
    {
        return $this->resolvableType;
    }
}
