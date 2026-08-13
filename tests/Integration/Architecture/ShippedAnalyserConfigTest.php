<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\TestCase;

use function class_exists;
use function file_get_contents;
use function preg_match_all;
use function sprintf;

/**
 * Holds the two configs Gacela ships to consumers against the code they name.
 *
 * `phpstan-gacela.neon` is loaded into a user's analysis automatically, through
 * `extra.phpstan.includes`, and `psalm-gacela.xml` is the file its own comments
 * tell them to XInclude. Both are hand-written lists of fully qualified class
 * names -- the shape that goes stale on a rename and says nothing until someone
 * else's build breaks.
 *
 * The *active* services are already exercised: {@see \GacelaTest\Integration\PHPStan\PhpStanFixtureTestCase}
 * runs the shipped neon for real, so a missing one fails PHPStan's boot there.
 * The commented-out opt-in rules are not, and they are the ones a consumer
 * copies by hand -- a renamed class leaves instructions that produce
 * "Service of type ... not found" the moment somebody follows them.
 */
final class ShippedAnalyserConfigTest extends TestCase
{
    private const PHPSTAN_CONFIG = __DIR__ . '/../../../phpstan-gacela.neon';

    private const PSALM_CONFIG = __DIR__ . '/../../../psalm-gacela.xml';

    /**
     * Commented entries included on purpose: being opt-in is why nothing else
     * catches them.
     */
    public function test_every_class_the_shipped_phpstan_config_names_exists(): void
    {
        $classes = $this->classNamesIn(self::PHPSTAN_CONFIG, '~^\s*#?\s*class:\s*([A-Za-z0-9_\\\\]+)~m');

        self::assertNotSame([], $classes, 'no service entries found -- the parsing, not the config, is wrong');

        foreach ($classes as $class) {
            self::assertTrue(
                class_exists($class),
                sprintf('phpstan-gacela.neon registers "%s", which does not exist', $class),
            );
        }
    }

    /**
     * The plugin cannot be delivered through the XInclude, so the file spells
     * out the class for the reader to register by hand. Spelling is all it is.
     */
    public function test_the_plugin_the_shipped_psalm_config_names_exists(): void
    {
        $classes = $this->classNamesIn(self::PSALM_CONFIG, '~pluginClass\s+class="([A-Za-z0-9_\\\\]+)"~');

        self::assertNotSame([], $classes, 'no pluginClass found -- the parsing, not the config, is wrong');

        foreach ($classes as $class) {
            self::assertTrue(
                class_exists($class),
                sprintf('psalm-gacela.xml tells consumers to register "%s", which does not exist', $class),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function classNamesIn(string $file, string $pattern): array
    {
        $contents = (string)file_get_contents($file);
        preg_match_all($pattern, $contents, $found);

        /** @var list<string> $classes */
        $classes = array_values(array_unique($found[1]));

        return $classes;
    }
}
