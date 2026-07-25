<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\TestCase;

use function class_exists;
use function count;
use function dirname;
use function file_get_contents;
use function method_exists;
use function preg_match_all;
use function sprintf;
use function trait_exists;

/**
 * Guards `infection.json5` against the two ways it fails silently.
 *
 * An ignore entry naming a method that no longer exists is dead config: the
 * mutant it was meant to exempt comes back, and nothing says the entry stopped
 * matching. And `infection.json5` is JSON5, where a duplicate mutator key is
 * accepted with last-one-wins — so a second block for the same mutator quietly
 * discards the first.
 *
 * Both happened while ratcheting `Gacela\Console\*` into the mutation gate. Both
 * are cheap to detect and invisible otherwise.
 */
final class InfectionConfigTest extends TestCase
{
    public function test_every_ignored_method_still_exists(): void
    {
        preg_match_all('/"([A-Za-z0-9_\\\\]+)::([A-Za-z0-9_]+)"/', $this->configContents(), $matches, PREG_SET_ORDER);

        self::assertNotEmpty($matches, 'no ignore entries found — has the config format changed?');

        foreach ($matches as [$entry, $class, $method]) {
            $unescaped = str_replace('\\\\', '\\', $class);

            self::assertTrue(
                class_exists($unescaped) || trait_exists($unescaped),
                sprintf('infection.json5 ignores %s, but that class does not exist', $entry),
            );
            self::assertTrue(
                method_exists($unescaped, $method),
                sprintf('infection.json5 ignores %s, but that method does not exist', $entry),
            );
        }
    }

    public function test_no_mutator_is_configured_twice(): void
    {
        preg_match_all('/^        ([A-Za-z_]+): \{/m', $this->configContents(), $matches);

        $seen = [];
        foreach ($matches[1] as $mutator) {
            self::assertArrayNotHasKey(
                $mutator,
                $seen,
                sprintf('infection.json5 configures "%s" twice; JSON5 keeps only the last block', $mutator),
            );
            $seen[$mutator] = true;
        }

        self::assertGreaterThan(0, count($seen));
    }

    private function configContents(): string
    {
        return (string)file_get_contents(dirname(__DIR__, 3) . '/infection.json5');
    }
}
