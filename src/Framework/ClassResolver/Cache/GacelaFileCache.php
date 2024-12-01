<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Gacela\Framework\Config\ConfigInterface;

final class GacelaFileCache
{
    public const KEY_ENABLED = 'gacela-cache-enabled';

    public const DEFAULT_ENABLED_VALUE = false;

    public const DEFAULT_DIRECTORY_VALUE = null;

    private static ?bool $isEnabled = null;

    public function __construct(
        private readonly ConfigInterface $config,
    ) {
    }

    /**
     * @internal
     */
    public static function resetCache(): void
    {
        self::$isEnabled = null;
    }

    public function isEnabled(): bool
    {
        if (self::$isEnabled === null) {
            self::$isEnabled = $this->config->hasKey(self::KEY_ENABLED)
                ? (bool)$this->config->get(self::KEY_ENABLED)
                : $this->config->getSetupGacela()->isFileCacheEnabled();
        }

        return self::$isEnabled;
    }
}
