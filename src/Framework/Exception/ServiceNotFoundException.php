<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

use function sprintf;

final class ServiceNotFoundException extends RuntimeException
{
    public function __construct(string $className)
    {
        parent::__construct(sprintf('Service "%s" not found in the container.', $className));
    }
}
