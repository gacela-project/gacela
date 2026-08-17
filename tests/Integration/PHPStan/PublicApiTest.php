<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use GacelaTest\Integration\PublicApiFixture\Reporting\ReadsAnEventHandler;
use GacelaTest\Integration\PublicApiFixture\Reporting\ReadsThePublishedInvoice;
use GacelaTest\Integration\PublicApiFixture\Reporting\ReadsTheRepository;
use GacelaTest\Integration\PublicApiFixture\Reporting\ReadsTheSharedSummary;
use Override;

/**
 * Runs PHPStan for real over the same fixture and the same expectations that
 * {@see \GacelaTest\Integration\Psalm\PublicApiTest} hands Psalm.
 *
 * The pair is the test. `#[PublicApi]` is read by plain reflection in the shared
 * analyser precisely so the two hosts cannot answer differently, and nothing but
 * running both of them says whether that held.
 *
 * Nothing configures the public API here: the neon leaves `publicApiSegments`
 * out, so what silences `Billing\Shared\` is the default a project gets for free.
 */
final class PublicApiTest extends PhpStanFixtureTestCase
{
    /**
     * The precondition for every silent assertion below: a receiver nothing
     * publishes is still reported, so the check really did run.
     */
    public function test_a_class_nothing_publishes_is_still_reported(): void
    {
        self::assertStringContainsString(ReadsTheRepository::class, $this->analyseFixture());
    }

    public function test_a_class_the_owning_module_published_by_attribute_is_silent(): void
    {
        self::assertStringNotContainsString(ReadsThePublishedInvoice::class, $this->analyseFixture());
    }

    /**
     * With no `publicApiSegments` written anywhere, which is the point: a project
     * that puts its shapes under `Shared\` needs no configuration and no
     * annotation.
     */
    public function test_a_class_under_a_published_sub_namespace_is_silent_by_default(): void
    {
        self::assertStringNotContainsString(ReadsTheSharedSummary::class, $this->analyseFixture());
    }

    /**
     * `EventHandler` merely starts with the published segment `Event`. On a
     * prefix match every module's handlers would be exported on a naming
     * coincidence.
     */
    public function test_a_namespace_that_merely_starts_with_a_published_segment_is_reported(): void
    {
        self::assertStringContainsString(ReadsAnEventHandler::class, $this->analyseFixture());
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/../PublicApiFixture/phpstan-public-api.neon';
    }
}
