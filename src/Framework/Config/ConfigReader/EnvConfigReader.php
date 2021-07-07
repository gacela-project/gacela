<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\ConfigReader;

use Gacela\Framework\Config\ConfigReaderInterface;

final class EnvConfigReader implements ConfigReaderInterface
{
    public function canRead(string $file): bool
    {
        return false !== strpos($file, '.env');
    }

    public function read(string $fullPath): array
    {
        $config = [];

        $lines = file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
