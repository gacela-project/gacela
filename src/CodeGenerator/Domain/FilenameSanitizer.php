<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain;

use RuntimeException;

final class FilenameSanitizer
{
    public const FACADE = 'Facade';
    public const FACTORY = 'Factory';
    public const CONFIG = 'Config';
    public const DEPENDENCY_PROVIDER = 'DependencyProvider';

    private const EXPECTED_FILENAMES = [
        self::FACADE,
        self::FACTORY,
        self::CONFIG,
        self::DEPENDENCY_PROVIDER,
    ];

    public function sanitize(string $filename): string
    {
        $percents = [];
        foreach (self::EXPECTED_FILENAMES as $expected) {
            $percents[$expected] = similar_text($expected, $filename, $percent);
        }

        $maxVal = max($percents);
        $maxValKeys = array_keys($percents, $maxVal, true);

        if (count($maxValKeys) > 1) {
            throw new RuntimeException(sprintf(
                'Which filename do you mean [%s]?',
                implode(' or ', $maxValKeys)
            ));
        }

        return reset($maxValKeys);
    }
}
