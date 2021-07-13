<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\ConfigReader;

use Gacela\Framework\Config\ConfigReaderInterface;
use Symfony\Component\Yaml\Yaml;

final class YamlConfigReader implements ConfigReaderInterface
{
    public function canRead(string $absolutePath): bool
    {
        $extension = pathinfo($absolutePath, PATHINFO_EXTENSION);

        return 'yaml' === $extension || 'yml' === $extension;
    }

    public function read(string $absolutePath): array
    {
        if (!file_exists($absolutePath)) {
            return [];
        }

        /** @var null|array $content */
        $content = Yaml::parseFile($absolutePath);

        return is_array($content) ? $content : [];
    }
}
