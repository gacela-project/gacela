<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\Deprecation;

final readonly class TDeprecationInfo
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
        public string $elementName,
        public string $elementType,
        public string $since,
        public ?string $replacement,
        public ?string $willRemoveIn,
        public ?string $reason,
        public string $file,
        public int $line,
    ) {
    }
}
