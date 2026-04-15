<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

use RuntimeException;

use function sprintf;

final class CycleDetectedException extends RuntimeException
{
    public static function between(string $childKey, string $parentKey): self
    {
        return new self(sprintf(
            'Refusing to add dependency "%s" -> "%s": parent already depends on child (cycle).',
            $childKey,
            $parentKey,
        ));
    }

    public static function selfDependency(string $key): self
    {
        return new self(sprintf('A cache entry cannot depend on itself ("%s").', $key));
    }
}
