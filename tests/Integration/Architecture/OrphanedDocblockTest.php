<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function count;
use function dirname;
use function file_get_contents;
use function implode;
use function is_array;
use function is_dir;
use function sprintf;
use function str_replace;
use function token_get_all;

/**
 * Two docblocks in a row, which is what a displaced one looks like.
 *
 * Adding a method directly above an existing one, between its docblock and its
 * signature, leaves the first method undocumented and the second carrying a
 * description of something else -- along with whatever `@param` or `@var` the
 * displaced block held. Both halves stay valid php, so nothing objects: the
 * analysers only notice when the annotation was load-bearing for a type, which
 * is how one of these reached `main` and stayed.
 *
 * There were nine in `src/` when this test was written, and three more
 * introduced in a single afternoon. It is an easy edit to make and an easy one
 * to miss in review, which is exactly the kind a test should own.
 *
 * `tests/` is not covered yet -- the same fault is there and is tracked
 * separately, so this starts where a wrong `@param` reaches a reader of the
 * shipped code.
 */
final class OrphanedDocblockTest extends TestCase
{
    /** @var list<string> */
    private const SHIPPED_DIRECTORIES = [
        'src',
        'symfony-bridge/src',
        'laravel-bridge/src',
    ];

    public function test_no_docblock_describes_another_docblock(): void
    {
        $orphans = [];

        foreach (self::SHIPPED_DIRECTORIES as $directory) {
            foreach ($this->phpFilesIn($directory) as $file) {
                foreach ($this->orphansIn($file) as $line) {
                    $orphans[] = sprintf('%s:%d', $this->relative($file), $line);
                }
            }
        }

        self::assertSame([], $orphans, sprintf(
            "A docblock is followed by another docblock, so it describes nothing:\n  %s\n"
            . 'Move it onto the member it was written for.',
            implode("\n  ", $orphans),
        ));
    }

    /**
     * The scan finds something, so an empty result means the shipped code is
     * clean rather than the walk being broken.
     */
    public function test_the_scan_reaches_the_shipped_code(): void
    {
        $files = [];
        foreach (self::SHIPPED_DIRECTORIES as $directory) {
            foreach ($this->phpFilesIn($directory) as $file) {
                $files[] = $file;
            }
        }

        self::assertGreaterThan(100, count($files), 'the walk found almost nothing -- the paths, not the code, are wrong');
    }

    /**
     * A docblock whose next meaningful token is another docblock.
     *
     * Tokenised rather than matched with a regex: `/**` inside a string or a
     * heredoc is not a docblock, and the tokenizer is the thing that already
     * knows the difference.
     *
     * @return list<int> the line each orphan starts on
     */
    private function orphansIn(string $file): array
    {
        $tokens = token_get_all((string)file_get_contents($file));
        $count = count($tokens);
        $orphans = [];

        for ($i = 0; $i < $count; ++$i) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] !== T_DOC_COMMENT) {
                continue;
            }

            for ($j = $i + 1; $j < $count; ++$j) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }

                if (is_array($tokens[$j]) && $tokens[$j][0] === T_DOC_COMMENT) {
                    $orphans[] = $tokens[$i][2];
                }

                break;
            }
        }

        return $orphans;
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        $root = $this->projectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
        if (!is_dir($root)) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        ) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = (string)$file->getRealPath();
            }
        }

        return $files;
    }

    private function relative(string $file): string
    {
        return str_replace($this->projectRoot() . DIRECTORY_SEPARATOR, '', $file);
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }
}
