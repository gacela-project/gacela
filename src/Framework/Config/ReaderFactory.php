<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\ConfigReader\EnvConfigReader;
use Gacela\Framework\Config\ConfigReader\PhpConfigReader;
use RuntimeException;

final class ReaderFactory
{
    private const VALID_TYPES = ['php', 'env'];

    public static function create(string $type): ConfigReaderInterface
    {
        switch ($type) {
            case 'php':
                return new PhpConfigReader();
            case 'env':
                return new EnvConfigReader();
        }

        throw new RuntimeException(sprintf(
            'ConfigReader type not supported: "%s". Valid types: %s',
            $type,
            implode(',', self::VALID_TYPES)
        ));
    }
}
