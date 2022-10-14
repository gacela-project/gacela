<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Profiler;

use Gacela\Framework\Config\ConfigInterface;

final class GacelaProfiler
{
    public const KEY_ENABLED = 'gacela-profiler-enabled';
    public const DEFAULT_ENABLED_VALUE = false;
    public const DEFAULT_DIRECTORY_VALUE = '.gacela/profiler';

    private ConfigInterface $config;

    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        if ($this->config->hasKey(self::KEY_ENABLED)) {
            return (bool)$this->config->get(self::KEY_ENABLED);
        }

        return $this->config->getSetupGacela()->isProfilerEnabled();
    }
}
