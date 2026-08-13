<?php

declare(strict_types=1);

namespace Gacela\Framework\Preload;

use function count;
use function implode;
use function sprintf;

/**
 * What a preload run managed to link, and what it could not.
 *
 * The skipped names are carried rather than counted: a class that fails to link
 * is silently missing from the preload image, so the one chance to notice is
 * the line written at startup.
 */
final class PreloadResult
{
    /**
     * @param list<class-string> $linked
     * @param list<string> $skipped
     */
    public function __construct(
        private readonly array $linked,
        private readonly array $skipped,
    ) {
    }

    public function linkedCount(): int
    {
        return count($this->linked);
    }

    /**
     * @return list<string>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /**
     * The line an operator reads at startup. It names what was skipped rather
     * than only counting it: a count says the image is incomplete, the names
     * say which class is being loaded per request instead.
     */
    public function summary(): string
    {
        $summary = sprintf(
            'Gacela Opcache Preload: %d classes linked, %d skipped',
            count($this->linked),
            count($this->skipped),
        );

        if ($this->skipped === []) {
            return $summary;
        }

        return $summary . ' (' . implode(', ', $this->skipped) . ')';
    }
}
