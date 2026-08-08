<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Psalm\ReportedIssues;
use PHPUnit\Framework\TestCase;
use Psalm\Issue\PluginIssue;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_diff;
use function array_unique;
use function array_values;
use function class_exists;
use function file_get_contents;
use function is_subclass_of;
use function preg_match_all;
use function sort;

/**
 * Holds the identifier-to-issue map complete in **both** directions.
 *
 * PHPStan reports an identifier; Psalm reports an issue class, and that class
 * name is what a consumer suppresses on. A rule whose identifier is missing from
 * the map is a rule with no way to turn it off, and it would go unnoticed --
 * nothing else fails when a violation is quietly dropped.
 *
 * The other direction matters just as much: a map entry that no rule produces
 * any more is a suppression key that silently stopped meaning anything.
 */
final class ReportedIssuesTest extends TestCase
{
    private const RULES_DIR = __DIR__ . '/../../../src/StaticAnalysis/Rules';

    public function test_every_identifier_a_rule_reports_has_an_issue_class(): void
    {
        self::assertSame(
            [],
            array_values(array_diff($this->identifiersInRules(), ReportedIssues::mappedIdentifiers())),
            'a rule reports an identifier Psalm has no issue class for, so it cannot be suppressed',
        );
    }

    public function test_the_map_carries_no_identifier_a_rule_stopped_reporting(): void
    {
        self::assertSame(
            [],
            array_values(array_diff(ReportedIssues::mappedIdentifiers(), $this->identifiersInRules())),
            'a mapped identifier no rule produces is a suppression key that means nothing',
        );
    }

    public function test_every_mapped_issue_is_a_psalm_plugin_issue(): void
    {
        foreach (ReportedIssues::mappedIdentifiers() as $identifier) {
            $issueClass = ReportedIssues::issueFor($identifier);

            self::assertNotNull($issueClass, $identifier . ' maps to nothing');
            self::assertTrue(class_exists($issueClass), $issueClass . ' does not exist');
            self::assertTrue(
                is_subclass_of($issueClass, PluginIssue::class),
                $issueClass . ' must extend PluginIssue to be suppressible by name',
            );
        }
    }

    public function test_an_identifier_no_rule_reports_maps_to_nothing(): void
    {
        self::assertNull(ReportedIssues::issueFor('gacela.notARule'));
    }

    /**
     * Read out of the rules themselves rather than listed here, so adding a rule
     * with a new identifier fails this test instead of passing a copy of it.
     *
     * @return list<string>
     */
    private function identifiersInRules(): array
    {
        $found = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::RULES_DIR)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all("/'(gacela\\.[A-Za-z]+)'/", (string)file_get_contents($file->getPathname()), $matches);
            foreach ($matches[1] as $identifier) {
                $found[] = $identifier;
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }
}
