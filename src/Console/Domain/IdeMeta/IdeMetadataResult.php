<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\IdeMeta;

final class IdeMetadataResult
{
    /**
     * @param bool $changed whether the file on disk already said this
     * @param array<string, list<class-string>> $ambiguous ids reported instead of written
     */
    public function __construct(
        public readonly string $path,
        public readonly bool $changed,
        public readonly int $typedIds,
        public readonly array $ambiguous = [],
    ) {
    }
}
