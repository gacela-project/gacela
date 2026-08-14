<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\UndiscoveredFacadeFile;
use Gacela\Console\Domain\AllAppModules\UndiscoveredFacadeProblem;

use function count;
use function sprintf;

/**
 * Reports a file named like a Facade that discovery did not turn into a module.
 *
 * Every other check starts from the modules that *were* found, so a module that
 * was never found is invisible to all of them. `list:modules` says "no modules
 * found" and names the cause only when nothing at all was discovered -- one
 * broken Facade in fifty leaves forty-nine modules and silence.
 *
 * Reported as a warning rather than an error because `Facade` is an ordinary
 * word: a project using Gacela beside a framework with its own facades has
 * classes ending in it that were never meant to be modules. `--strict` fails
 * the run for a project that wants it to.
 */
final class UndiscoveredFacadeCheck implements HealthCheck
{
    /**
     * @param list<UndiscoveredFacadeFile> $undiscovered
     */
    public function __construct(
        private readonly array $undiscovered,
    ) {
    }

    public function name(): string
    {
        return 'undiscovered facades';
    }

    public function run(): CheckResult
    {
        if ($this->undiscovered === []) {
            return CheckResult::ok($this->name(), 'every Facade-named file resolves to a module');
        }

        $details = [];
        foreach ($this->undiscovered as $facade) {
            $details[] = sprintf(
                '%s — %s (%s)',
                $facade->className,
                $facade->problem === UndiscoveredFacadeProblem::NotLoadable
                    ? 'php cannot load this class'
                    : 'does not extend AbstractFacade',
                $facade->path,
            );
        }

        return CheckResult::warn(
            $this->name(),
            $details,
            $this->remediation(),
        );
    }

    private function remediation(): string
    {
        $notLoadable = 0;
        foreach ($this->undiscovered as $facade) {
            if ($facade->problem === UndiscoveredFacadeProblem::NotLoadable) {
                ++$notLoadable;
            }
        }

        if ($notLoadable === count($this->undiscovered)) {
            return 'check the psr-4 prefix covers the directory and the namespace declaration '
                . 'matches the path, then `composer dump-autoload`';
        }

        if ($notLoadable === 0) {
            return 'extend AbstractFacade, or rename the class if it was never meant to be a module';
        }

        return 'for the unloadable ones check the psr-4 prefix and `composer dump-autoload`; '
            . 'for the rest extend AbstractFacade, or rename the class if it was never meant to be a module';
    }
}
