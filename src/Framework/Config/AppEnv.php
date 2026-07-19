<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use function getenv;

/**
 * Single source of truth for the application environment name, so the
 * env-suffixed config file lookup and the merged-config cache filename can
 * never disagree within one bootstrap.
 */
final class AppEnv
{
    public static function current(): string
    {
        return getenv('APP_ENV') ?: '';
    }
}
