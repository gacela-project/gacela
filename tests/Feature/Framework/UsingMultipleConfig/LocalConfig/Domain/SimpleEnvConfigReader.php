<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingMultipleConfig\LocalConfig\Domain;

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
            if (strncmp(trim($line), '#', 1) === 0) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $config[trim($name)] = trim($value);
        }

        return $config;
    }

    private function canRead(string $absolutePath): bool
    {
        return false !== strpos($absolutePath, '.env')
            && is_file($absolutePath);
    }
}
