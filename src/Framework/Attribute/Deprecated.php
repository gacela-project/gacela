<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY)]
final readonly class Deprecated
{
    /**
     * @param non-empty-string $since Version when the deprecation was introduced
     * @param non-empty-string|null $replacement Suggested replacement class/method
     * @param non-empty-string|null $willRemoveIn Version when it will be removed
     * @param non-empty-string|null $reason Additional context about the deprecation
     */
    public function __construct(
        public string $since,
        public ?string $replacement = null,
        public ?string $willRemoveIn = null,
        public ?string $reason = null,
    ) {
    }
}
