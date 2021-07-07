<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\ConfigReader;

use Gacela\Framework\Config\ConfigReaderInterface;

final class PhpConfigReader implements ConfigReaderInterface
{
    public function read(string $fullPath): array
    {
        if (file_exists($fullPath)) {
            /** @var null|array $content */
            $content = include $fullPath;

            return is_array($content) ? $content : [];
        }

        return [];
    }
}
