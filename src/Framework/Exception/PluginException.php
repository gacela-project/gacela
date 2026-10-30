<?php

declare(strict_types=1);

namespace Gacela\Framework\Exception;

use RuntimeException;

use function sprintf;

final class PluginException extends RuntimeException
{
    public static function notCallable(string $plugin): self
    {
        return new self(sprintf(
            'Plugin "%s" is not an invokable class, a callable, or a service the container can build.',
            $plugin,
        ));
    }
}
