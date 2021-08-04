<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItemInterface;
use RuntimeException;

final class ConfigException extends RuntimeException
{
    /**
     * @param array<string,GacelaConfigItemInterface> $configs
     */
    public static function notFound(string $type, array $configs): self
    {
        return new self(sprintf(
            'Config-type not found in the Gacela config file: "%s". Only found: %s',
            $type,
            implode(', ', array_keys($configs))
        ));
    }
}
