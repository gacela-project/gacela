<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use Override;

/**
 * The same fixture with a lone empty `<publicApiSegment/>`, which is how Psalm
 * spells "the convention is off, leave me the attribute".
 *
 * Twin of {@see \GacelaTest\Integration\PHPStan\PublicApiConventionOffTest}: the
 * two hosts have to make the same thing writable, or turning a convention off
 * would mean turning it off in one analyser and living with it in the other.
 */
final class PublicApiConventionOffTest extends PsalmFixtureTestCase
{
    public function test_an_empty_segment_element_puts_the_sub_namespace_back_under_the_rule(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString('GacelaCrossModule', $this->errorsIn('ReadsTheSharedSummary.php'));
    }

    /**
     * The attribute is not a convention and does not go away with one.
     */
    public function test_the_attribute_still_publishes_with_the_convention_off(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('GacelaCrossModule', $errors, 'precondition: the check ran at all');
        self::assertSame('', $this->errorsIn('ReadsThePublishedInvoice.php'));
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/../PublicApiFixture/psalm-public-api-off.xml';
    }
}
