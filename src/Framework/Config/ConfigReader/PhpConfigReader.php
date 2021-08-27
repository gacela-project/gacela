<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\ConfigReader;

use Gacela\Framework\Config\ConfigReaderInterface;
use JsonSerializable;
use RuntimeException;
use function is_array;

final class PhpConfigReader implements ConfigReaderInterface
{
    public function canRead(string $absolutePath): bool
    {
        $extension = pathinfo($absolutePath, PATHINFO_EXTENSION);

        return 'php' === $extension;
    }

    /**
     * @return array<string,mixed>
     */
    public function read(string $absolutePath): array
    {
        if (!file_exists($absolutePath)) {
            return [];
        }

        /** @var null|string[]|JsonSerializable|mixed $content */
        $content = include $absolutePath;

        if (null === $content) {
            return [];
        }

        if ($content instanceof JsonSerializable) {
            /** @var array<string,mixed> $jsonSerialized */
            $jsonSerialized = $content->jsonSerialize();
            return $jsonSerialized;
        }

        if (!is_array($content)) {
            throw new RuntimeException('The PHP config file must return an array or a JsonSerializable object!');
        }

        /** @var array<string,mixed> $content */
        return $content;
    }
}
