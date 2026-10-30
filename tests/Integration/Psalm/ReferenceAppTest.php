<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use Override;

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
    public function test_the_reference_application_has_no_findings(): void
    {
        $output = $this->analyseFixture('json');
        $this->skipIfPsalmCannotRun($output);
        self::assertStringNotContainsString('ServiceMapAccessors is internal', $output, 'The framework trait must be usable outside Gacela.');

        $findings = ReferenceAppDiagnostics::in($output);

        self::assertSame([], $findings, 'psalm reported: ' . json_encode($findings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/../../Feature/ReferenceApp/psalm-reference-app.xml';
    }
}
