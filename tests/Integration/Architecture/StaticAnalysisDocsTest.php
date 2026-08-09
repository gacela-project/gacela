<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use Gacela\Psalm\ReportedIssues;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function preg_match_all;
use function sprintf;

/**
 * Holds the rule table in `docs/static-analysis.md` against the code.
 *
 * That table is not decoration: an error identifier and an issue class name are
 * what a consumer writes into `ignoreErrors` or `<issueHandlers>` to turn one
 * rule off. A documented pair that no longer exists is a suppression that
 * silently stops working, and a rule missing from the table is one nobody can
 * find the key for.
 */
final class StaticAnalysisDocsTest extends TestCase
{
    private const DOCS = __DIR__ . '/../../../docs/static-analysis.md';

    public function test_every_rule_is_documented_with_both_of_its_suppression_keys(): void
    {
        $documented = $this->documentedPairs();

        foreach (ReportedIssues::mappedIdentifiers() as $identifier) {
            $issue = ReportedIssues::issueFor($identifier);
            self::assertNotNull($issue);

            self::assertArrayHasKey(
                $identifier,
                $documented,
                sprintf('%s is reported by a rule but missing from the table in docs/static-analysis.md', $identifier),
            );

            self::assertSame(
                $this->shortName($issue),
                $documented[$identifier],
                sprintf('docs/static-analysis.md pairs %s with the wrong psalm issue', $identifier),
            );
        }
    }

    public function test_the_table_documents_no_rule_that_stopped_existing(): void
    {
        foreach ($this->documentedPairs() as $identifier => $issue) {
            self::assertNotNull(
                ReportedIssues::issueFor($identifier),
                sprintf('docs/static-analysis.md documents %s, which no rule reports any more', $identifier),
            );
        }
    }

    /**
     * The suppression examples name a real rule, so a rename cannot leave a
     * consumer copying a key that does nothing.
     */
    public function test_the_suppression_examples_name_a_real_rule(): void
    {
        $docs = $this->docs();

        self::assertStringContainsString('identifier: gacela.suffixExtends', $docs);
        self::assertStringContainsString('<PluginIssue name="GacelaSuffixExtends">', $docs);
        self::assertNotNull(ReportedIssues::issueFor('gacela.suffixExtends'));
    }

    /**
     * Read out of the markdown table rows, which look like:
     * `| what it checks | `gacela.x` | `GacelaX` | on |`
     *
     * @return array<string, string> identifier => psalm issue short name
     */
    private function documentedPairs(): array
    {
        preg_match_all('/`(gacela\.[A-Za-z]+)`\s*\|\s*`(Gacela[A-Za-z]+)`/', $this->docs(), $matches, PREG_SET_ORDER);

        $pairs = [];
        foreach ($matches as $match) {
            $pairs[$match[1]] = $match[2];
        }

        return $pairs;
    }

    private function docs(): string
    {
        return (string)file_get_contents(self::DOCS);
    }

    private function shortName(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts);
    }
}
