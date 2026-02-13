<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\Deprecation;

final class TDeprecationInfo
{
    /**
     * @param non-empty-string $elementName
     * @param non-empty-string $elementType
     * @param non-empty-string $since
     * @param non-empty-string|null $replacement
     * @param non-empty-string|null $willRemoveIn
     * @param non-empty-string|null $reason
     * @param non-empty-string $file
     */
    public function __construct(
        public readonly string $elementName,
        public readonly string $elementType,
        public readonly string $since,
        public readonly ?string $replacement,
        public readonly ?string $willRemoveIn,
        public readonly ?string $reason,
        public readonly string $file,
        public readonly int $line,
    ) {
    }
}
