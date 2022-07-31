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
        $this->resolvableType = $resolvableType;
        $this->className = $className;
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
