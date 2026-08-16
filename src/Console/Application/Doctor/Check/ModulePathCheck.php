<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;

use function count;
use function sprintf;

/**
 * Reports an `appModulePaths` entry that is not a directory.
 *
 * Discovery skips one, and every module-scoped check works from the modules
 * discovery returned -- so a single mistyped entry narrows all of them at once
 * and the run still ends in a screen of ticks. That is the shape of failure
 * this command exists to catch: nothing is broken, everything passes, and the
 * part of the project nobody looked at is the part that was wrong.
 *
 * Gacela already `trigger_error`s about these while building the iterator, on
 * a stream a command's own output may not share and which a reader scanning a
 * report has no reason to be watching. The report says it where the reader is.
 *
 * A warning rather than an error, matching {@see UndiscoveredFacadeCheck}: a
 * project may point at a directory some environment generates, and a naming
 * coincidence should not fail a build on its own. `--strict` is how a project
 * that knows its paths are fixed opts into failing.
 */
final class ModulePathCheck implements HealthCheck
{
    /**
     * @param list<string> $scannedPaths configured entries discovery walked
     * @param list<string> $unscannedPaths configured entries that are not directories
     */
    public function __construct(
        private readonly array $scannedPaths,
        private readonly array $unscannedPaths,
    ) {
    }

    public function name(): string
    {
        return 'module paths';
    }

    public function run(): CheckResult
    {
        if ($this->unscannedPaths === []) {
            return CheckResult::ok(
                $this->name(),
                sprintf('%d configured path(s) scanned', count($this->scannedPaths)),
            );
        }

        $details = [];
        foreach ($this->unscannedPaths as $path) {
            $details[] = sprintf(
                'appModulePaths entry "%s" is not a directory, so nothing under it was scanned',
                $path,
            );
        }

        return CheckResult::warn(
            $this->name(),
            $details,
            'fix the path, or drop the entry, in gacela.php with `GacelaConfig::setAppModulePaths()`',
        );
    }
}
