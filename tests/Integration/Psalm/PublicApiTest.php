<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use Override;

/**
 * The Psalm half of {@see \GacelaTest\Integration\PHPStan\PublicApiTest}: same
 * fixture, same configuration written the other way, same expectations.
 *
 * A module's public surface that held in one analyser and not the other would be
 * a surface nobody could rely on, which is why the attribute is read by plain
 * reflection in the shared analyser rather than through each host's own API.
 */
final class PublicApiTest extends PsalmFixtureTestCase
{
    /**
     * The precondition for every silent assertion below: a receiver nothing
     * publishes is still reported, so the check really did run.
     */
    public function test_a_class_nothing_publishes_is_still_reported(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('GacelaCrossModule', $this->errorsIn('ReadsTheRepository.php'));
    }

    public function test_a_class_the_owning_module_published_by_attribute_is_silent(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('GacelaCrossModule', $errors, 'precondition: the check ran at all');
        self::assertSame('', $this->errorsIn('ReadsThePublishedInvoice.php'));
    }

    public function test_a_class_under_a_published_sub_namespace_is_silent_by_default(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('GacelaCrossModule', $errors, 'precondition: the check ran at all');
        self::assertSame('', $this->errorsIn('ReadsTheSharedSummary.php'));
    }

    /**
     * `EventHandler` merely starts with the published segment `Event`. On a
     * prefix match every module's handlers would be exported on a naming
     * coincidence.
     */
    public function test_a_namespace_that_merely_starts_with_a_published_segment_is_reported(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString('GacelaCrossModule', $this->errorsIn('ReadsAnEventHandler.php'));
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/../PublicApiFixture/psalm-public-api.xml';
    }
}
