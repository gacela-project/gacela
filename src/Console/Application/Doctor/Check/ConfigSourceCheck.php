<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;

use function count;
use function sprintf;

/**
 * Whether the config paths a project declared actually match a file.
 *
 * `addAppConfig('conf/*.php')` for a directory named `config` bootstraps
 * perfectly: globbing a path that is not there yields no files, no file yields
 * no values, and an application with no configuration is a legitimate one. What
 * fails is the first thing to read a key, with an error about the key rather
 * than about the path that was supposed to provide it.
 *
 * {@see ConfigSchemaCheck} only sees this when the project declared a schema to
 * check the values against. This one needs no schema: a path that matches
 * nothing is worth saying out loud whatever the values were meant to be.
 */
final class ConfigSourceCheck implements HealthCheck
{
    /**
     * @param list<string> $unmatchedPatterns declared config patterns that matched no file
     * @param int $declaredCount how many config paths the project declared in total
     */
    public function __construct(
        private readonly array $unmatchedPatterns,
        private readonly int $declaredCount,
    ) {
    }

    public function name(): string
    {
        return 'config sources';
    }

    public function run(): CheckResult
    {
        if ($this->declaredCount === 0) {
            return CheckResult::ok($this->name(), 'no config paths declared — nothing to load');
        }

        if ($this->unmatchedPatterns === []) {
            return CheckResult::ok(
                $this->name(),
                sprintf('%d declared config path(s) match files', $this->declaredCount),
            );
        }

        return CheckResult::warn(
            $this->name(),
            array_map(
                static fn (string $pattern): string => sprintf('%s matches no file', $pattern),
                $this->unmatchedPatterns,
            ),
            sprintf(
                '%d of %d declared config path(s) load nothing, so every value they were meant to provide is missing. Correct the path passed to addAppConfig(), or drop it.',
                count($this->unmatchedPatterns),
                $this->declaredCount,
            ),
        );
    }
}
