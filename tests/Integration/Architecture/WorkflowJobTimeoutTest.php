<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function count;
use function dirname;
use function is_array;
use function preg_match;
use function sprintf;

/**
 * Guards that every CI job caps its own runtime.
 *
 * A job without `timeout-minutes` inherits GitHub's 6-hour default. A runner
 * that stalls on checkout or `setup-php` therefore holds the PR's checks open
 * for six hours, and only a human with `gh run cancel` -- followed by an
 * explicit `gh run rerun`, because a cancel alone marks the run failed --
 * can clear it. That happened five times in one day on `phpbench.yml`.
 *
 * With a timeout the same stall surfaces as an ordinary red check anyone can
 * re-run. The values are sized generously against observed durations, so this
 * test also refuses a timeout so large it re-creates the problem it fixes.
 */
final class WorkflowJobTimeoutTest extends TestCase
{
    /**
     * No job in this repository legitimately runs this long; the slowest is
     * the full mutation-testing sweep. A larger value is a typo, not a need.
     */
    private const int MAX_TIMEOUT_MINUTES = 60;

    /**
     * @return iterable<string, array{string, string, int|null}>
     */
    public static function workflowJobProvider(): iterable
    {
        foreach (self::workflowFiles() as $file) {
            $name = basename($file);

            foreach (self::jobTimeoutsIn($file) as $job => $timeout) {
                yield sprintf('%s: %s', $name, $job) => [$name, $job, $timeout];
            }
        }
    }

    #[DataProvider('workflowJobProvider')]
    public function test_workflow_job_declares_a_timeout(string $workflow, string $job, ?int $timeout): void
    {
        self::assertNotNull($timeout, sprintf(
            "Job '%s' in %s has no timeout-minutes, so it inherits GitHub's 6-hour default. "
            . 'A stalled runner would block the PR until somebody cancels it by hand.',
            $job,
            $workflow,
        ));
    }

    #[DataProvider('workflowJobProvider')]
    public function test_workflow_job_timeout_stays_within_reach(string $workflow, string $job, ?int $timeout): void
    {
        if ($timeout === null) {
            self::markTestSkipped('Covered by test_workflow_job_declares_a_timeout.');
        }

        self::assertGreaterThan(0, $timeout, sprintf(
            "Job '%s' in %s declares timeout-minutes: %d.",
            $job,
            $workflow,
            $timeout,
        ));

        self::assertLessThanOrEqual(self::MAX_TIMEOUT_MINUTES, $timeout, sprintf(
            "Job '%s' in %s declares timeout-minutes: %d. Nothing here runs that long; "
            . 'a cap above %d leaves a stalled runner blocking the PR, which is what the cap is for.',
            $job,
            $workflow,
            $timeout,
            self::MAX_TIMEOUT_MINUTES,
        ));
    }

    public function test_every_workflow_declares_at_least_one_job(): void
    {
        foreach (self::workflowFiles() as $file) {
            self::assertNotEmpty(self::jobTimeoutsIn($file), sprintf(
                '%s parsed to no jobs at all; the guard would pass vacuously.',
                basename($file),
            ));
        }
    }

    public function test_the_repository_has_workflows_to_check(): void
    {
        self::assertGreaterThan(1, count(self::workflowFiles()));
    }

    /**
     * @return list<string>
     */
    private static function workflowFiles(): array
    {
        $found = glob(dirname(__DIR__, 3) . '/.github/workflows/*.{yml,yaml}', GLOB_BRACE);

        return is_array($found) ? $found : [];
    }

    /**
     * Maps each job id declared under the top-level `jobs:` key to its
     * `timeout-minutes`, or to null when it declares none.
     *
     * Job ids sit at two spaces of indentation and their own keys at four, so
     * a line parser separates them without a YAML extension -- which this
     * repository does not require, and which would only be needed here.
     *
     * @return array<string, int|null>
     */
    private static function jobTimeoutsIn(string $file): array
    {
        $jobs = [];
        $current = null;
        $inJobs = false;

        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^jobs:\s*$/', $line) === 1) {
                $inJobs = true;
                continue;
            }

            if (!$inJobs) {
                continue;
            }

            if (preg_match('/^\S/', $line) === 1) {
                break;
            }

            if (preg_match('/^ {2}([A-Za-z0-9_-]+):\s*$/', $line, $matches) === 1) {
                $current = $matches[1];
                $jobs[$current] = null;
                continue;
            }

            if ($current !== null && preg_match('/^ {4}timeout-minutes:\s*(\d+)\s*$/', $line, $matches) === 1) {
                $jobs[$current] = (int)$matches[1];
            }
        }

        return $jobs;
    }
}
