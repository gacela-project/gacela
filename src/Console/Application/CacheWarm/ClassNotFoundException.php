<?php

declare(strict_types=1);

namespace Gacela\Console\Application\CacheWarm;

use RuntimeException;

use function sprintf;

final class ClassNotFoundException extends RuntimeException
{
    public function __construct(string $className)
    {
        parent::__construct(sprintf('Class not found: %s', $className));
    }
}
