<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use stdClass;
use UnexpectedValueException;

use function is_array;
use function is_string;

final class ReferenceAppDiagnostics
{
    /**
     * @return list<stdClass>
     */
    public static function in(string $json): array
    {
        $findings = [];
        foreach (self::decode($json) as $diagnostic) {
            if (!$diagnostic instanceof stdClass || !is_string($diagnostic->file_path ?? null)) {
                throw new UnexpectedValueException('Expected a diagnostic file_path.');
            }

            if (str_contains(str_replace('\\', '/', $diagnostic->file_path), '/tests/Feature/ReferenceApp/Invoicing/')) {
                $findings[] = $diagnostic;
            }
        }

        return $findings;
    }

    /**
     * @return array<mixed>
     */
    private static function decode(string $json): array
    {
        $diagnostics = json_decode($json, flags: JSON_THROW_ON_ERROR);
        if (!is_array($diagnostics)) {
            throw new UnexpectedValueException('Expected a list of Psalm diagnostics.');
        }

        return $diagnostics;
    }

}
