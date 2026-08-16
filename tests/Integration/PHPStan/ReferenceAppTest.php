<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use Override;

use function array_filter;
use function array_values;
use function explode;
use function implode;
use function preg_match;

/**
 * The reference application, analysed the way a project would analyse itself.
 *
 * `phpstan-tests.neon` covers `tests/` at level 0 and without the Gacela rules,
 * which is right for a tree full of deliberately broken fixtures and wrong for
 * the one thing in it that is meant to be exemplary. So the application gets its
 * own config -- level max, the shipped rules, and the three opt-in ones on --
 * and this drives it.
 *
 * A rule that starts reporting the reference application is either a rule that
 * changed or an application that stopped being a good example, and both are
 * worth a failing build.
 */
final class ReferenceAppTest extends PhpStanFixtureTestCase
{
    /**
     * Findings only, not the whole output: `--error-format=raw` prints one
     * `path:line:message` per finding, and PHPStan puts its own result-cache
     * and runtime diagnostics on the same stream when it feels like it. An
     * assertion on emptiness therefore failed on a clean application, roughly
     * one run in three.
     */
    public function test_the_reference_application_has_no_findings(): void
    {
        $output = $this->analyseFixture();

        self::assertStringNotContainsString('Uncaught', $output, 'phpstan crashed: ' . $output);

        $findings = array_values(array_filter(
            explode("\n", $output),
            static fn (string $line): bool => preg_match('#Invoicing.+\.php:\d+:#', $line) === 1,
        ));

        self::assertSame([], $findings, "phpstan reported:\n" . implode("\n", $findings));
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/../../Feature/ReferenceApp/phpstan-reference-app.neon';
    }
}
