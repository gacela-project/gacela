<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ForeignCache;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Cacheable
{
    public function __construct(public int $ttl = 60, public ?string $key = null)
    {
    }
}
