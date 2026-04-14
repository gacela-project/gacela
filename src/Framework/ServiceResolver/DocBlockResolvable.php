<?php

declare(strict_types=1);

namespace Gacela\Framework\ServiceResolver;

use function sprintf;

final class DocBlockResolvable
{
    /** @var class-string */
    private readonly string $className;

    /**
     * @param class-string $className
     */
    public function __construct(
        string $className,
        private readonly string $resolvableType,
    ) {
        /** @psalm-suppress PropertyTypeCoercion */
        $this->className = sprintf('\\%s', ltrim($className, '\\')); // @phpstan-ignore-line
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
