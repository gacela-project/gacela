<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ComposerAutoload;

use function is_array;
use function is_string;

final class Psr4Directory
{
    public static function firstOf(mixed $directories): string
    {
        if (is_string($directories)) {
            return $directories;
        }

        if (!is_array($directories)) {
            return '';
        }

        return isset($directories[0]) && is_string($directories[0]) ? $directories[0] : '';
    }
}
