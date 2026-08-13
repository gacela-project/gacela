<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function file_get_contents;
use function preg_match_all;
use function realpath;
use function sprintf;

/**
 * Every tip `ErrorSuggestionHelper` can produce is reachable from `src/`.
 *
 * `addHelpfulTip()` dispatches on a string with `default => ''`, so an arm
 * nobody passes is indistinguishable from one nobody has written: the method
 * returns an empty string either way and no test fails. Two of them went dead
 * that way. `facade_not_found` was orphaned when the resolver started phrasing
 * its tips per kind, and `class_not_found` was never wired at all -- reachable
 * only from its own unit test, which passed and proved nothing about whether a
 * developer would ever see it.
 *
 * Asked of the source text rather than of behaviour, because there is no
 * behaviour to ask: a dead arm is silent by construction.
 */
final class HelpfulTipsAreReachableTest extends TestCase
{
    private const HELPER = __DIR__ . '/../../../src/Framework/Exception/ErrorSuggestionHelper.php';

    public function test_every_tip_context_is_passed_by_production_code(): void
    {
        $contexts = $this->declaredContexts();

        self::assertNotSame([], $contexts, 'no match arms found -- the parsing, not the helper, is wrong');

        $callers = $this->productionSources();

        foreach ($contexts as $context) {
            self::assertStringContainsString(
                sprintf("addHelpfulTip('%s')", $context),
                $callers,
                sprintf(
                    'ErrorSuggestionHelper offers a "%s" tip that no production code asks for, '
                    . 'so nobody can ever read it. Wire it up or drop the arm.',
                    $context,
                ),
            );
        }
    }

    /**
     * The reverse direction: a caller naming a context the helper does not
     * define gets `default => ''` and no error, so a typo silently removes the
     * tips from an exception without failing anything.
     */
    public function test_every_context_production_code_passes_is_defined(): void
    {
        $declared = $this->declaredContexts();

        preg_match_all("~addHelpfulTip\('([a-z_]+)'\)~", $this->productionSources(), $found);

        /** @var list<string> $passed */
        $passed = $found[1];

        self::assertNotSame([], $passed, 'no calls found -- the parsing, not the callers, is wrong');

        foreach ($passed as $context) {
            self::assertContains(
                $context,
                $declared,
                sprintf('"%s" is passed to addHelpfulTip() but has no arm, so it silently yields no tips', $context),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function declaredContexts(): array
    {
        preg_match_all("~^\s+'([a-z_]+)' => \"~m", (string)file_get_contents(self::HELPER), $matches);

        /** @var list<string> $contexts */
        $contexts = $matches[1];

        return $contexts;
    }

    /**
     * Every production source but the helper itself, whose own match arms would
     * otherwise count as their own callers.
     */
    private function productionSources(): string
    {
        $sources = '';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator((string)realpath(__DIR__ . '/../../../src')),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (!str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            if ($file->getFilename() === 'ErrorSuggestionHelper.php') {
                continue;
            }

            $sources .= (string)file_get_contents($file->getPathname());
        }

        return $sources;
    }
}
