<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function count;
use function dirname;
use function is_array;
use function method_exists;
use function preg_match_all;
use function sprintf;

/**
 * A doctor finding is only worth printing if its remediation can be followed.
 *
 * The suffix-mismatch check told the reader to "add the missing suffix via
 * `SuffixTypesBuilder::addFacade` in gacela.php". `SuffixTypesBuilder` is a
 * private collaborator of `GacelaConfig`, and what gacela.php hands you is the
 * `GacelaConfig` -- which has no `addFacade()` at all. The method that does the
 * job is `addSuffixTypeFacade()`. So the one line printed to get somebody
 * unstuck named an API they could not call from the place it named.
 *
 * Advice drifts the way any prose beside code drifts, and nothing else reads
 * it. This walks the API references in the checks' own string literals and
 * asks the only question that matters about each: does it exist.
 */
final class RemediationNamesRealApiTest extends TestCase
{
    /**
     * Backticks are how these strings mark an API reference, which keeps this
     * away from ordinary prose that happens to contain a `::`.
     */
    private const string REFERENCE_PATTERN = '/`\\\\?([A-Za-z_][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)\(\)`/';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function apiReferenceProvider(): iterable
    {
        $index = self::classIndex();

        foreach (self::checkFiles() as $file) {
            foreach (self::stringLiteralsOf($file) as $literal) {
                if (preg_match_all(self::REFERENCE_PATTERN, $literal, $matches, PREG_SET_ORDER) === false) {
                    continue;
                }

                foreach ($matches as $match) {
                    [, $shortName, $method] = $match;

                    yield sprintf('%s: %s::%s()', basename($file), $shortName, $method) => [
                        basename($file),
                        $index[$shortName] ?? $shortName,
                        $method,
                    ];
                }
            }
        }
    }

    #[DataProvider('apiReferenceProvider')]
    public function test_an_api_named_in_a_check_message_exists(string $file, string $class, string $method): void
    {
        self::assertTrue(class_exists($class) || interface_exists($class), sprintf(
            '%s names `%s::%s()`, and no such class is declared under src/.',
            $file,
            $class,
            $method,
        ));

        self::assertTrue(method_exists($class, $method), sprintf(
            "%s tells the reader to call `%s::%s()`, which does not exist.\n"
            . 'A remediation naming an uncallable method costs the reader the search it was printed to save.',
            $file,
            $class,
            $method,
        ));
    }

    public function test_there_are_api_references_to_check(): void
    {
        self::assertGreaterThan(0, count([...self::apiReferenceProvider()]));
    }

    /**
     * @return list<string>
     */
    private static function checkFiles(): array
    {
        $found = glob(dirname(__DIR__, 3) . '/src/Console/Application/Doctor/Check/*.php');

        return is_array($found) ? $found : [];
    }

    /**
     * Only the strings the commands print, never the code around them: a
     * remediation is prose, and prose is what goes stale unnoticed.
     *
     * @return list<string>
     */
    private static function stringLiteralsOf(string $file): array
    {
        $source = file_get_contents($file);
        if ($source === false) {
            return [];
        }

        $literals = [];
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $literals[] = $token[1];
            }
        }

        return $literals;
    }

    /**
     * Short name to fully qualified name for everything under `src/`, so a
     * message can name `GacelaConfig` the way a reader would write it.
     *
     * @return array<string, string>
     */
    private static function classIndex(): array
    {
        $root = dirname(__DIR__, 3) . '/src';
        $index = [];

        /** @var iterable<string, SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($files as $path => $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents((string)$path);
            if ($source === false) {
                continue;
            }

            if (preg_match('#^namespace\s+(.+);#m', $source, $namespaceMatch) !== 1) {
                continue;
            }

            $shortName = $file->getBasename('.php');
            $index[$shortName] = $namespaceMatch[1] . '\\' . $shortName;
        }

        return $index;
    }
}
