<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

final class StubPublishResult
{
    /**
     * @param list<string> $written stubs now in the project
     * @param list<string> $skipped stubs already there, left as the project edited them
     */
    public function __construct(
        public readonly array $written,
        public readonly array $skipped,
    ) {
    }

    public function hasSkipped(): bool
    {
        return $this->skipped !== [];
    }
}
