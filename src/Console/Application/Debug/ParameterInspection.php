<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

final class ParameterInspection
{
    public function __construct(
        public readonly string $name,
        public readonly string $renderedType,
        public readonly ParameterStatus $status,
        public readonly string $detail,
    ) {
    }

    public function isResolvable(): bool
    {
        return $this->status->isResolvable();
    }
}
