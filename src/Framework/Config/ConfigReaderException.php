<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use RuntimeException;

final class ConfigReaderException extends RuntimeException
{
    /**
     * @param array<string,ConfigReaderInterface> $readers
     */
    public static function notSupported(string $type, array $readers): self
    {
        return new self(sprintf(
            'ConfigReader type not supported: "%s". Valid types: %s',
            $type,
            implode(',', array_keys($readers))
        ));
    }
}
