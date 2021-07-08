<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\ConfigReader;

use Gacela\Framework\Config\ConfigReaderInterface;

final class EnvConfigReader implements ConfigReaderInterface
{
    public function canRead(string $absolutePath): bool
    {
        return false !== strpos($absolutePath, '.env');
    }

    public function read(string $absolutePath): array
    {
        $config = [];

        $lines = file($absolutePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $config[trim($name)] = trim($value);
        }

        return $config;
    }
}
