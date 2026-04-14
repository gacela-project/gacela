<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Exception;

use Gacela\Framework\Exception\ErrorSuggestionHelper;
use PHPUnit\Framework\TestCase;

final class ErrorSuggestionHelperTest extends TestCase
{
    public function test_suggest_similar_returns_empty_string_when_no_options_available(): void
    {
        self::assertSame('', ErrorSuggestionHelper::suggestSimilar('anything', []));
    }

    public function test_suggest_similar_returns_empty_string_when_no_option_is_similar_enough(): void
    {
        self::assertSame('', ErrorSuggestionHelper::suggestSimilar('abc', ['xyz', 'qrs', 'uvw']));
    }

    public function test_suggest_similar_returns_exact_formatted_output_with_one_match(): void
    {
        $expected = "\n\nDid you mean?\n  - userName";

        self::assertSame(
            $expected,
            ErrorSuggestionHelper::suggestSimilar('user_name', ['userName', 'xyz', 'qrs']),
        );
    }

    public function test_suggest_similar_prefixes_each_match_with_bullet_dash(): void
    {
        $actual = ErrorSuggestionHelper::suggestSimilar('apple', ['apples', 'apply', 'appliance']);

        self::assertStringStartsWith("\n\nDid you mean?\n  - ", $actual);
        self::assertStringContainsString("\n  - ", $actual);
    }

    public function test_suggest_similar_sorts_by_highest_similarity_first(): void
    {
        // Options are intentionally given in ASCENDING similarity order; the
        // helper must sort them DESCENDING so arsort() cannot be silently
        // removed without breaking this expectation.
        $actual = ErrorSuggestionHelper::suggestSimilar(
            'userName',
            ['userN', 'userNa', 'userNam'],
        );

        self::assertSame(
            "\n\nDid you mean?\n  - userNam\n  - userNa\n  - userN",
            $actual,
        );
    }

    public function test_suggest_similar_caps_results_at_three(): void
    {
        $actual = ErrorSuggestionHelper::suggestSimilar(
            'userName',
            ['userNam', 'userNa', 'userN', 'userNameX', 'userNameY'],
        );

        self::assertSame(3, substr_count($actual, "\n  - "));
    }

    public function test_suggest_similar_is_case_insensitive(): void
    {
        $actual = ErrorSuggestionHelper::suggestSimilar('USERNAME', ['username']);

        self::assertSame("\n\nDid you mean?\n  - username", $actual);
    }

    public function test_suggest_similar_lowercases_option_too(): void
    {
        // Forces strtolower() on BOTH arguments of similar_text: search is
        // lowercase, option is uppercase, so only the option needs
        // normalising for the comparison to succeed.
        $actual = ErrorSuggestionHelper::suggestSimilar('username', ['USERNAME']);

        self::assertSame("\n\nDid you mean?\n  - USERNAME", $actual);
    }

    public function test_suggest_similar_uses_strict_greater_than_threshold(): void
    {
        // similar_text('abc', 'xyz') returns 0 → below the 0.4 threshold → excluded
        $actual = ErrorSuggestionHelper::suggestSimilar('abc', ['xyz']);

        self::assertSame('', $actual);
    }

    public function test_suggest_similar_excludes_options_with_low_similarity_score(): void
    {
        // similar_text('hello', 'hel') ≈ 75% → included
        // similar_text('hello', 'xy') → low → excluded
        $actual = ErrorSuggestionHelper::suggestSimilar('hello', ['hel', 'xy']);

        self::assertStringContainsString('  - hel', $actual);
        self::assertStringNotContainsString('  - xy', $actual);
    }

    public function test_suggest_similar_treats_two_empty_strings_as_identical(): void
    {
        // Exercises the `$longer === 0 → return 1.0` branch in calculateSimilarity.
        // With both searchTerm and option empty, the method short-circuits to 1.0
        // (above threshold) so the empty option is reported as a suggestion.
        $actual = ErrorSuggestionHelper::suggestSimilar('', ['']);

        self::assertSame("\n\nDid you mean?\n  - ", $actual);
    }

    public function test_add_helpful_tip_for_class_not_found(): void
    {
        $expected = "\n\nTips:\n"
            . "  • Check your class namespace\n"
            . "  • Ensure the file exists in the correct location\n"
            . "  • Run 'composer dump-autoload' to refresh autoloader\n"
            . '  • Verify PSR-4 namespace mapping in composer.json';

        self::assertSame($expected, ErrorSuggestionHelper::addHelpfulTip('class_not_found'));
    }

    public function test_add_helpful_tip_for_service_not_found(): void
    {
        $expected = "\n\nTips:\n"
            . "  • Check if the service is registered in a Provider\n"
            . "  • Verify the service binding in gacela.php\n"
            . '  • Ensure the service class exists and is autoloadable';

        self::assertSame($expected, ErrorSuggestionHelper::addHelpfulTip('service_not_found'));
    }

    public function test_add_helpful_tip_for_facade_not_found(): void
    {
        $expected = "\n\nTips:\n"
            . "  • Ensure your Facade extends AbstractFacade\n"
            . "  • Check the module namespace matches the directory structure\n"
            . '  • Verify the Facade file name matches the class name';

        self::assertSame($expected, ErrorSuggestionHelper::addHelpfulTip('facade_not_found'));
    }

    public function test_add_helpful_tip_for_config_error(): void
    {
        $expected = "\n\nTips:\n"
            . "  • Check your gacela.php configuration file\n"
            . "  • Ensure all configuration values are valid\n"
            . '  • Review the documentation: https://gacela-project.com/docs/';

        self::assertSame($expected, ErrorSuggestionHelper::addHelpfulTip('config_error'));
    }

    public function test_add_helpful_tip_for_unknown_context_returns_empty_string(): void
    {
        self::assertSame('', ErrorSuggestionHelper::addHelpfulTip('unknown'));
        self::assertSame('', ErrorSuggestionHelper::addHelpfulTip(''));
    }
}
