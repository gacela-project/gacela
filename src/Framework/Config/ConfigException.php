<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use RuntimeException;

final class ConfigException extends RuntimeException
{
    /**
     * @param array<string,GacelaJsonConfigItem> $configs
     */
    public static function notFound(string $type, array $configs): self
    {
        return new self(sprintf(
            'Config type not found in gacela.json: "%s". Only found: %s',
            $type,
            implode(', ', array_keys($configs))
        ));
    }
}
