<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\DtoGenerate;

use function count;

final class DtoGenerateResult
{
    /**
     * @param array<string, string> $written class name => file it was written to
     * @param list<string> $unchanged classes whose file already said this
     * @param list<string> $unplaceable classes no autoload prefix covers
     */
    public function __construct(
        public readonly array $written,
        public readonly array $unchanged,
        public readonly array $unplaceable,
    ) {
    }

    public function hasChanges(): bool
    {
        return $this->written !== [];
    }

    public function total(): int
    {
        return count($this->written) + count($this->unchanged) + count($this->unplaceable);
    }
}
