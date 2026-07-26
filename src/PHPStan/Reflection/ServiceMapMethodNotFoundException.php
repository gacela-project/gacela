<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Reflection;

use RuntimeException;

use function sprintf;

final class ServiceMapMethodNotFoundException extends RuntimeException
{
    public function __construct(string $className, string $methodName)
    {
        parent::__construct(sprintf(
            'No #[ServiceMap] on "%s" declares the method "%s".',
            $className,
            $methodName,
        ));
    }
}
