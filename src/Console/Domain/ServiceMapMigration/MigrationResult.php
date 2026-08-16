<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ServiceMapMigration;

use function count;

/**
 * What migrating one file would do, without having done it.
 *
 * The rewritten code is carried rather than written, so `--dry-run` and the
 * real run answer the same question and only differ in whether anything is
 * saved. A preview that took a second path could disagree with the run it
 * previews, which is the one thing a preview must not do.
 */
final class MigrationResult
{
    /**
     * @param list<string> $declared `Class::accessor()` for each attribute added
     */
    public function __construct(
        public readonly string $path,
        public readonly string $originalCode,
        public readonly string $migratedCode,
        public readonly array $declared,
    ) {
    }

    public static function unchanged(string $path, string $code): self
    {
        return new self($path, $code, $code, []);
    }

    public function hasChanges(): bool
    {
        return $this->declared !== [];
    }

    public function declaredCount(): int
    {
        return count($this->declared);
    }
}
