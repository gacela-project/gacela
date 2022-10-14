<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Gacela\Framework\Config\ConfigInterface;

final class GacelaCache
{
    public const KEY_ENABLED = 'gacela-cache-enabled';
    public const DEFAULT_ENABLED_VALUE = false;
    public const DEFAULT_DIRECTORY_VALUE = '.gacela/cache';

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
        return $this->config->getSetupGacela()->isCacheEnabled();
    }

    private function isCacheFromApplicationConfigEnabled(): bool
    {
        return (bool)$this->config->get(self::KEY_ENABLED, self::DEFAULT_ENABLED_VALUE);
    }
}
