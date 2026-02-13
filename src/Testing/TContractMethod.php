<?php

declare(strict_types=1);

namespace Gacela\Testing;

final class TContractMethod
{
    /**
     * @param non-empty-string $name
     * @param list<non-empty-string> $parameters
     * @param non-empty-string|null $returnType
     */
    public function __construct(
        public readonly string $name,
        public readonly array $parameters,
        public readonly ?string $returnType,
        public readonly bool $isPublic = true,
    ) {
    }
}
