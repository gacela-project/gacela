<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use GacelaTest\Integration\PublicApiFixture\Reporting\ReadsThePublishedInvoice;
use GacelaTest\Integration\PublicApiFixture\Reporting\ReadsTheSharedSummary;
use Override;

/**
 * The same fixture with `publicApiSegments: []`, for the project that would
 * rather annotate every export than agree to a convention.
 *
 * Written as its own config rather than as a flag, because "the default applies"
 * and "the convention is off" are two different runs of the same code over the
 * same classes, and only the difference between them is worth asserting.
 */
final class PublicApiConventionOffTest extends PhpStanFixtureTestCase
{
    public function test_an_empty_segment_list_puts_the_sub_namespace_back_under_the_rule(): void
    {
        self::assertStringContainsString(ReadsTheSharedSummary::class, $this->analyseFixture());
    }

    /**
     * The attribute is not a convention and does not go away with one.
     */
    public function test_the_attribute_still_publishes_with_the_convention_off(): void
    {
        self::assertStringNotContainsString(ReadsThePublishedInvoice::class, $this->analyseFixture());
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/../PublicApiFixture/phpstan-public-api-off.neon';
    }
}
