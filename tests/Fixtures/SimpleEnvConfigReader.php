<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures;

use Gacela\Framework\Config\ConfigReaderInterface;

final class SimpleEnvConfigReader implements ConfigReaderInterface
{
    /**
     * @return array<string,mixed>
     */
    public function read(string $absolutePath): array
    {
        if (!$this->canRead($absolutePath)) {
            return [];
        }

        $config = [];

        /** @var string[] $lines */
        $lines = file($absolutePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $config[trim($name)] = trim($value);
        }

        return $config;
    }

    private function canRead(string $absolutePath): bool
    {
        return str_contains($absolutePath, '.env')
            && is_file($absolutePath);
    }
}
