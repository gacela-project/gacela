<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Gacela\Framework\Config\ConfigInterface;

final class GacelaFileCache
{
    public const KEY_ENABLED = 'gacela-cache-enabled';
    public const DEFAULT_DIRECTORY_VALUE = '.gacela/cache';
    public const DEFAULT_FILE_CACHE_ENABLED_VALUE = false;
    public const DEFAULT_SHOULD_RESET_IN_MEMORY_CACHE_VALUE = false;

    private ConfigInterface $config;

    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return $this->isCacheFromSetupEnabled()
            || $this->isCacheFromApplicationConfigEnabled();
    }

    private function isCacheFromSetupEnabled(): bool
    {
        return $this->config->getSetupGacela()->isFileCacheEnabled();
    }

    private function isCacheFromApplicationConfigEnabled(): bool
    {
        return (bool)$this->config->get(self::KEY_ENABLED, self::DEFAULT_FILE_CACHE_ENABLED_VALUE);
    }
}
