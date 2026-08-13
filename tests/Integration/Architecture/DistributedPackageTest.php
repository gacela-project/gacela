<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function exec;
use function implode;
use function sprintf;
use function trim;

/**
 * What a consumer receives when they `composer require` this package.
 *
 * The dist is the working tree minus everything `.gitattributes` marks
 * `export-ignore`, so that file decides what ships -- and getting it wrong is
 * silent in both directions. Export-ignoring `resources/` would publish a
 * package whose documented `opcache.preload` target does not exist; forgetting
 * to ignore a dev config would ship this repository's own `phpstan.neon` into
 * everyone else's analysis.
 *
 * Asked of git rather than of a built tarball: `git ls-files` plus
 * `check-attr export-ignore` is exactly the pair that decides membership, and
 * it needs no archive to be created to answer.
 */
final class DistributedPackageTest extends TestCase
{
    protected function setUp(): void
    {
        if ($this->git('rev-parse --is-inside-work-tree') !== 'true') {
            self::markTestSkipped('not a git checkout, so there is no dist to reason about');
        }
    }

    /**
     * Files a consumer's tooling loads by name. Each is referenced from
     * somewhere outside this repository -- composer's `extra.phpstan.includes`,
     * an XInclude, or a php.ini `opcache.preload`.
     *
     * @return iterable<string, array{string}>
     */
    public static function shippedPaths(): iterable
    {
        yield 'phpstan extension' => ['phpstan-gacela.neon'];
        yield 'psalm fallback config' => ['psalm-gacela.xml'];
        yield 'opcache preload script' => ['resources/gacela-preload.php'];
        yield 'the framework itself' => ['src/Framework/Gacela.php'];
        yield 'the console binary' => ['bin/gacela'];
    }

    /**
     * This repository's own tooling. Shipping any of it means a consumer's
     * analysis or test run picks up configuration written for Gacela's source
     * tree, not theirs.
     *
     * @return iterable<string, array{string}>
     */
    public static function withheldPaths(): iterable
    {
        yield 'dev phpstan config' => ['phpstan.neon'];
        yield 'dev psalm config' => ['psalm.xml'];
        yield 'phpunit config' => ['phpunit.xml'];
        yield 'infection config' => ['infection.json5'];
        yield 'the test suite' => ['tests'];
    }

    #[DataProvider('shippedPaths')]
    public function test_a_path_consumers_load_is_in_the_dist(string $path): void
    {
        self::assertTrue($this->isTracked($path), sprintf('%s is not tracked, so it cannot ship', $path));
        self::assertTrue(
            $this->shipped($path),
            sprintf('%s is missing from the dist, so consumers never receive it', $path),
        );
    }

    #[DataProvider('withheldPaths')]
    public function test_this_repositorys_own_tooling_stays_here(string $path): void
    {
        self::assertTrue($this->isTracked($path), sprintf('%s is not tracked -- fix the fixture, not the rule', $path));
        self::assertFalse(
            $this->shipped($path),
            sprintf("%s ships, and it is written for this source tree rather than a consumer's", $path),
        );
    }

    private function isTracked(string $path): bool
    {
        return $this->git(sprintf('ls-files -- %s', escapeshellarg($path))) !== '';
    }

    /**
     * Asked of the archive itself, not of `check-attr`.
     *
     * `check-attr` answers for the path handed to it, and export-ignore is
     * normally written on a directory -- `/tests`, `/resources`. Exclusion
     * cascades to the contents; the attribute lookup on a child does not. A
     * guard built on `check-attr` therefore passes while the file it names is
     * absent from the dist, which is the one case it exists to catch.
     */
    private function shipped(string $path): bool
    {
        static $entries = null;

        if ($entries === null) {
            $output = [];
            exec(sprintf(
                'git -C %s archive --format=tar HEAD | tar -t 2>/dev/null',
                escapeshellarg(dirname(__DIR__, 3)),
            ), $output);

            $entries = $output;
        }

        foreach ($entries as $entry) {
            if ($entry === $path || str_starts_with($entry, rtrim($path, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    private function git(string $arguments): string
    {
        $output = [];
        exec(sprintf('git -C %s %s 2>/dev/null', escapeshellarg(dirname(__DIR__, 3)), $arguments), $output);

        return trim(implode("\n", $output));
    }
}
