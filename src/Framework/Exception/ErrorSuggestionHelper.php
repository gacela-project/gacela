<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use function array_map;
use function array_slice;
use function class_exists;
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
        if ($availableOptions === []) {
            return '';
        }

        $suggestions = self::findSimilar($searchTerm, $availableOptions);

        if ($suggestions === []) {
            return '';
        }

        return sprintf(
            "\n\nDid you mean?\n%s",
            implode("\n", array_map(static fn (string $s): string => '  - ' . $s, $suggestions)),
        );
    }

    /**
     * Tips for a class the resolver could not find, phrased in terms of the
     * kind it was actually looking for.
     *
     * These replace a fixed `facade_not_found` text that named `Facade` in
     * every message. The two exceptions carrying these tips are raised for a
     * `Provider` and for a docblock-declared kind, and a Facade is constructed
     * rather than resolved -- so the advice named the one kind it could never
     * be, directly under a message naming the right one.
     *
     * The base-class line is offered only where that base exists: the four
     * pillars have an `Abstract*`, a kind declared through
     * `addResolvableType()` has none, and inventing `AbstractExporter` sends
     * the reader looking for a class Gacela does not ship.
     */
    public static function addResolvableTypeTip(string $resolvableType): string
    {
        $tips = [];

        if (class_exists('Gacela\\Framework\\Abstract' . $resolvableType)) {
            $tips[] = sprintf('Ensure your %s extends Abstract%s', $resolvableType, $resolvableType);
        }

        $tips[] = 'Check the module namespace matches the directory structure';
        $tips[] = sprintf('Verify the %s file name matches the class name', $resolvableType);

        return sprintf(
            "\n\nTips:\n%s",
            implode("\n", array_map(static fn (string $t): string => '  • ' . $t, $tips)),
        );
    }

    public static function addHelpfulTip(string $context): string
    {
        return match ($context) {
            'class_not_found' => "\n\nTips:\n" .
                "  • Check your class namespace\n" .
                "  • Ensure the file exists in the correct location\n" .
                "  • Run 'composer dump-autoload' to refresh autoloader\n" .
                '  • Verify PSR-4 namespace mapping in composer.json',

            'service_not_found' => "\n\nTips:\n" .
                "  • Check if the service is registered in a Provider\n" .
                "  • Verify the service binding in gacela.php\n" .
                '  • Ensure the service class exists and is autoloadable',

            'config_error' => "\n\nTips:\n" .
                "  • Check your gacela.php configuration file\n" .
                "  • Ensure all configuration values are valid\n" .
                '  • Review the documentation: https://gacela-project.com/docs/',

            default => '',
        };
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

        return $percent / 100.0;
    }
}
