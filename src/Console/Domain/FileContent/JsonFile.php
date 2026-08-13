<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use function file_get_contents;
use function is_array;
use function is_file;
use function json_decode;

/**
 * Reads a json file, or answers that it could not.
 *
 * Absent, unreadable and malformed all collapse to null on purpose. Every
 * caller here is inspecting a manifest it does not own, where the three are the
 * same outcome -- there is nothing to read -- and telling them apart would only
 * grow branches that end in the same place.
 */
final class JsonFile
{
    /**
     * @return array<array-key, mixed>|null
     */
    public static function decode(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }
}
