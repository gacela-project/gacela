<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\IdeMeta;

use function count;

final class IdeMetadataResult
{
    /**
     * @param array<string, list<class-string>> $ambiguous ids reported instead of written
     */
    public function __construct(
        public readonly string $path,
        public readonly string $content,
        public readonly bool $changed,
        public readonly bool $written,
        public readonly int $typedIds,
        public readonly array $ambiguous = [],
    ) {
    }

    public function ambiguousCount(): int
    {
        return count($this->ambiguous);
    }
}
