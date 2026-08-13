<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Exception\ConfigDimensionException;

use function getenv;

/**
 * Single source of truth for the application environment name, so the
 * env-suffixed config file lookup and the merged-config cache filename can
 * never disagree within one bootstrap.
 */
final class AppEnv
{
    /**
     * @throws ConfigDimensionException when the value could not be part of a path
     */
    public static function current(): string
    {
        $env = getenv('APP_ENV') ?: '';

        // The same rule a declared dimension is held to, because this is the
        // first link of that chain and reaches the same two places. Checking it
        // here rather than at bootstrap keeps the answer and its validity in one
        // place: nothing can read APP_ENV past this method.
        ConfigDimensions::assertValueCanReachAPath('APP_ENV', $env);

        return $env;
    }
}
