<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\TestCase;

use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function glob;
use function implode;
use function in_array;
use function is_file;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function realpath;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;

use function strtolower;
use function trim;

use const PHP_EOL;

/**
 * Every relative link in the shipped markdown points at something that exists.
 *
 * Two of these have already been fixed by hand -- "Fix license link from readme"
 * and "Fix wrong link to contributing" -- which is the whole argument for the
 * test: a link is the one thing in a doc that nothing else reads.
 *
 * Anchors are checked as well as paths, and they are the half worth having. A
 * wrong path at least 404s on GitHub; a wrong anchor renders as an ordinary link
 * that quietly drops the reader at the top of the page, so the only way to
 * notice is to already know where you were meant to land. Renaming a heading is
 * how they break, and the rename never touches the file doing the linking.
 */
final class DocsLinksResolveTest extends TestCase
{
    private const string ROOT = __DIR__ . '/../../..';

    public function test_every_relative_link_points_at_a_file_that_exists(): void
    {
        $broken = [];

        foreach ($this->markdownFiles() as $file) {
            foreach ($this->relativeLinksIn($file) as $link) {
                [$path] = explode('#', $link, 2);
                if ($path === '') {
                    continue;
                }

                if (realpath(dirname($file) . '/' . $path) === false) {
                    $broken[] = sprintf('%s -> %s', $this->relative($file), $link);
                }
            }
        }

        self::assertSame([], $broken, 'links pointing at nothing:' . PHP_EOL . implode(PHP_EOL, $broken));
    }

    public function test_every_anchor_names_a_heading_in_the_file_it_points_at(): void
    {
        $broken = [];

        foreach ($this->markdownFiles() as $file) {
            foreach ($this->relativeLinksIn($file) as $link) {
                if (!str_contains($link, '#')) {
                    continue;
                }

                [$path, $anchor] = explode('#', $link, 2);
                $target = $path === '' ? $file : (string)realpath(dirname($file) . '/' . $path);

                // A missing file is the other test's finding, not this one's.
                if (!is_file($target)) {
                    continue;
                }

                if (!in_array($anchor, $this->headingSlugsIn($target), true)) {
                    $broken[] = sprintf('%s -> %s', $this->relative($file), $link);
                }
            }
        }

        self::assertSame([], $broken, 'anchors naming no heading:' . PHP_EOL . implode(PHP_EOL, $broken));
    }

    /**
     * Guards the two tests above against passing because they read nothing --
     * a glob that stops matching would leave both green and say nothing.
     */
    public function test_the_shipped_markdown_is_actually_being_read(): void
    {
        $files = $this->markdownFiles();

        self::assertGreaterThan(20, count($files));
        self::assertContains(realpath(self::ROOT . '/README.md'), $files);
        self::assertContains(realpath(self::ROOT . '/UPGRADE.md'), $files);
        self::assertContains(realpath(self::ROOT . '/docs/cli.md'), $files);
    }

    /**
     * @return list<string> absolute paths
     */
    private function markdownFiles(): array
    {
        $patterns = [
            self::ROOT . '/*.md',
            self::ROOT . '/docs/*.md',
            self::ROOT . '/docs/*/*.md',
            self::ROOT . '/*/README.md',
        ];

        $files = [];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $real = realpath($file);
                if ($real !== false) {
                    $files[$real] = $real;
                }
            }
        }

        return array_values($files);
    }

    /**
     * Inline links only. Bare `#fragment` links stay in, since they resolve
     * against the file they are written in.
     *
     * @return list<string>
     */
    private function relativeLinksIn(string $file): array
    {
        preg_match_all(
            '/\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/',
            (string)file_get_contents($file),
            $matches,
        );

        $links = [];
        foreach ($matches[1] as $link) {
            if (preg_match('~^(https?:|mailto:|//)~', $link) === 1) {
                continue;
            }

            $links[] = $link;
        }

        return $links;
    }

    /**
     * GitHub's slug: lowercased, code ticks and punctuation dropped, spaces to
     * hyphens. Link text replaces a linked heading, matching what GitHub does.
     *
     * Fenced blocks are skipped, or a `# Install` comment in a bash sample
     * would contribute a heading this file does not have -- which would not
     * fail anything, it would quietly let a genuinely broken anchor match.
     *
     * Repeats get GitHub's `-1`, `-2` suffixes, so a document with two headings
     * of one name can still be linked to twice.
     *
     * @return list<string>
     */
    private function headingSlugsIn(string $file): array
    {
        $slugs = [];
        $seen = [];
        $inFence = false;

        foreach (explode("\n", (string)file_get_contents($file)) as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line) === 1) {
                $inFence = !$inFence;
                continue;
            }

            if ($inFence) {
                continue;
            }

            if (preg_match('/^#{1,6}\s+(.*?)\s*$/', $line, $match) !== 1) {
                continue;
            }

            $text = strtolower($match[1]);
            $text = (string)preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text);
            $text = str_replace('`', '', $text);
            $text = (string)preg_replace('/[^a-z0-9\- ]/', '', $text);

            $slug = str_replace(' ', '-', trim($text));

            $seen[$slug] = ($seen[$slug] ?? -1) + 1;
            $slugs[] = $seen[$slug] === 0 ? $slug : $slug . '-' . $seen[$slug];
        }

        return $slugs;
    }

    private function relative(string $file): string
    {
        $root = realpath(self::ROOT);

        return $root !== false && str_starts_with($file, $root)
            ? substr($file, strlen($root) + 1)
            : $file;
    }
}
