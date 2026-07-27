<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

final class DependencyNode
{
    public function __construct(
        public readonly string $className,
        public readonly ProvisionStatus $status,
    ) {
    }

    public function isProvided(): bool
    {
        return $this->status->isProvided();
    }
}
