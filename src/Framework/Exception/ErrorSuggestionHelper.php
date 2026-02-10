<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use function array_slice;
use function count;
use function implode;
use function similar_text;
use function sprintf;
use function strlen;

final class ErrorSuggestionHelper
{
    private const SIMILARITY_THRESHOLD = 0.4;

    private const MAX_SUGGESTIONS = 3;

    /**
     * @param list<string> $availableOptions
     */
    public static function suggestSimilar(string $searchTerm, array $availableOptions): string
    {
        if (count($availableOptions) === 0) {
            return '';
        }

        $suggestions = self::findSimilar($searchTerm, $availableOptions);

        if (count($suggestions) === 0) {
            return '';
        }

        return sprintf(
            "\n\nDid you mean?\n%s",
            implode("\n", array_map(static fn (string $s): string => "  - {$s}", $suggestions)),
        );
    }

    public static function addHelpfulTip(string $context): string
    {
        $tips = match ($context) {
            'class_not_found' => "\n\nTips:\n" .
                "  • Check your class namespace\n" .
                "  • Ensure the file exists in the correct location\n" .
                "  • Run 'composer dump-autoload' to refresh autoloader\n" .
                '  • Verify PSR-4 namespace mapping in composer.json',

            'service_not_found' => "\n\nTips:\n" .
                "  • Check if the service is registered in a Provider\n" .
                "  • Verify the service binding in gacela.php\n" .
                '  • Ensure the service class exists and is autoloadable',

            'facade_not_found' => "\n\nTips:\n" .
                "  • Ensure your Facade extends AbstractFacade\n" .
                "  • Check the module namespace matches the directory structure\n" .
                '  • Verify the Facade file name matches the class name',

            'config_error' => "\n\nTips:\n" .
                "  • Check your gacela.php configuration file\n" .
                "  • Ensure all configuration values are valid\n" .
                '  • Review the documentation: https://gacela-project.com/docs/',

            default => '',
        };

        return $tips;
    }

    /**
     * @param list<string> $availableOptions
     *
     * @return list<string>
     */
    private static function findSimilar(string $searchTerm, array $availableOptions): array
    {
        $similarities = [];

        foreach ($availableOptions as $option) {
            $similarity = self::calculateSimilarity($searchTerm, $option);

            if ($similarity > self::SIMILARITY_THRESHOLD) {
                $similarities[$option] = $similarity;
            }
        }

        // Sort by similarity (highest first)
        arsort($similarities);

        return array_slice(array_keys($similarities), 0, self::MAX_SUGGESTIONS);
    }

    private static function calculateSimilarity(string $string1, string $string2): float
    {
        $longer = max(strlen($string1), strlen($string2));

        if ($longer === 0) {
            return 1.0;
        }

        similar_text(strtolower($string1), strtolower($string2), $percent);

        return $percent / 100;
    }
}
