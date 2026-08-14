<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\TestCase;

use function array_search;
use function count;
use function explode;
use function file_get_contents;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strtolower;

/**
 * Counts written into prose beside the list they count.
 *
 * `docs/cli.md` opens the doctor list with "Seven of the built-in checks report
 * the same kind of fault", and the bullets follow immediately. Adding one bullet
 * makes the sentence wrong and nothing says so -- twice in consecutive PRs
 * (#796 added a sixth bullet under "Five", #797 a seventh), both while the text
 * was still in `## Unreleased` and would have shipped.
 *
 * The list is what the reader trusts, so the number is checked against it rather
 * than the other way round.
 */
final class DocumentedCountsTest extends TestCase
{
    private const string CLI_DOCS = __DIR__ . '/../../../docs/cli.md';

    /** Spelled out in the prose, so matched that way. */
    private const array NUMBER_WORDS = [
        'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven',
        'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen',
        'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty',
    ];

    public function test_the_doctor_list_has_as_many_bullets_as_the_sentence_claims(): void
    {
        $lines = explode("\n", (string)file_get_contents(self::CLI_DOCS));

        $claimed = null;
        $bullets = 0;
        $counting = false;

        foreach ($lines as $line) {
            if (preg_match('/^(\w+) of the built-in checks report the same kind of fault/', $line, $match) === 1) {
                $claimed = $this->wordToNumber($match[1]);
                $counting = true;
                continue;
            }

            if (!$counting) {
                continue;
            }

            if (str_starts_with($line, '- **')) {
                ++$bullets;
                continue;
            }

            // The list ends at the next heading.
            if (str_starts_with($line, '## ')) {
                break;
            }
        }

        self::assertNotNull($claimed, 'the sentence introducing the doctor list is gone -- this test is now checking nothing');
        self::assertGreaterThan(0, $bullets, 'no bullets found under the sentence -- the parsing, not the docs, is wrong');

        self::assertSame($claimed, $bullets, sprintf(
            'docs/cli.md says %d of the built-in checks report that fault, and lists %d',
            $claimed,
            $bullets,
        ));
    }

    private function wordToNumber(string $word): ?int
    {
        $index = array_search(strtolower($word), self::NUMBER_WORDS, true);

        return $index === false ? null : $index;
    }
}
