<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use Override;

use function array_filter;
use function array_values;
use function explode;
use function implode;
use function str_contains;

/**
 * The reference application through the other analyser.
 *
 * The repo's own `psalm.xml` lists `src/` only, so without this the shipped
 * plugin is never pointed at an application -- and the cross-module and
 * declared-rules checks it can only do for an application are never exercised
 * end to end.
 */
final class ReferenceAppTest extends PsalmFixtureTestCase
{
    /**
     * Lines about the application, rather than the whole output: Psalm prints
     * nothing at all on a clean run, but it is not the only thing that can
     * reach this stream, and a crash is asserted on separately.
     */
    public function test_the_reference_application_has_no_findings(): void
    {
        $output = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($output);

        $findings = array_values(array_filter(
            explode("\n", $output),
            static fn (string $line): bool => str_contains($line, 'Invoicing' . DIRECTORY_SEPARATOR),
        ));

        self::assertSame([], $findings, "psalm reported:\n" . implode("\n", $findings));
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/../../Feature/ReferenceApp/psalm-reference-app.xml';
    }
}
