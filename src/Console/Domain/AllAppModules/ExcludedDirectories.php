<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\AllAppModules;

use function in_array;

/**
 * Decides which directories the module scan refuses to descend into.
 *
 * With no `appModulePaths` configured the scan starts at the project root, so
 * without pruning it walks everything: in this repository that is roughly
 * 110,000 files, about 11,700 of them under `vendor`. Rejecting them after the
 * fact still pays for the traversal.
 *
 * The rule is deliberately narrow. Anything hidden is skipped -- `.git`,
 * `.idea`, `.phpunit.cache` and friends are tooling state, never PSR-4 source --
 * plus the two dependency trees that are conventionally not source. Everything
 * else is descended into, because guessing that a project's `build/` or `data/`
 * holds no modules is how discovery starts silently missing them.
 */
final class ExcludedDirectories
{
    private const EXCLUDED_NAMES = ['vendor', 'node_modules'];

    public function isExcluded(string $directoryName): bool
    {
        return str_starts_with($directoryName, '.')
            || in_array($directoryName, self::EXCLUDED_NAMES, true);
    }
}
