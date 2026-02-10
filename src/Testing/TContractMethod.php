<?php

declare(strict_types=1);

namespace Gacela\Testing;

final readonly class TContractMethod
{
    /**
     * @param non-empty-string $name
     * @param list<non-empty-string> $parameters
     * @param non-empty-string|null $returnType
     */
    public function __construct(
        public string $name,
        public array $parameters,
        public ?string $returnType,
        public bool $isPublic = true,
    ) {
    }
}
