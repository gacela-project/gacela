<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\ConfigReader;

use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Event\ConfigReader\ReadPhpConfigEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use JsonSerializable;
use RuntimeException;

use function is_array;

final class PhpConfigReader implements ConfigReaderInterface
{
    use EventDispatchingCapabilities;

    /**
     * @return array<string,mixed>
     */
    public function read(string $absolutePath): array
    {
        if (!$this->canRead($absolutePath)) {
            return [];
        }

        self::dispatchEvent(new ReadPhpConfigEvent($absolutePath));

        /**
         * @psalm-suppress UnresolvableInclude
         *
         * @var null|string[]|JsonSerializable|mixed $content
         */
        $content = include $absolutePath;

        if ($content === null) {
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

    private function canRead(string $absolutePath): bool
    {
        $extension = pathinfo($absolutePath, PATHINFO_EXTENSION);

        return $extension === 'php' && file_exists($absolutePath);
    }
}
