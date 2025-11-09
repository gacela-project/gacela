<?php

declare(strict_types=1);

namespace Gacela\Framework\ServiceResolver;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class ServiceMap
{
    /**
     * @param class-string $className
     */
    public function __construct(
        public readonly string $method,
        public readonly string $className,
    ) {
    }
}
