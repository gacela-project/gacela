<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

interface CacheConfigurationInterface
{
    public function isFileCacheEnabled(): bool;

    public function getFileCacheDirectory(): string;

    public function shouldResetInMemoryCache(): bool;
}
