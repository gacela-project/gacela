<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\ConfigReader;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\EventListener\ConfigReader\GacelaConfigReaderListener;
use Gacela\Framework\EventListener\ConfigReader\ReadPhpConfigEvent;
use Gacela\Framework\EventListener\GacelaEventInterface;
use JsonSerializable;
use RuntimeException;

use function is_array;

final class PhpConfigReader implements ConfigReaderInterface
{
    /** @var array<string,list<callable>>*/
    private static array $listeners = [];

    /**
     * @return array<string,mixed>
     */
    public function read(string $absolutePath): array
    {
        if (!$this->canRead($absolutePath)) {
            return [];
        }

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

        $isReadable = $extension === 'php' && file_exists($absolutePath);

        if ($isReadable) {
            /** @psalm-suppress ArgumentTypeCoercion */
            $this->triggerEvent(new ReadPhpConfigEvent(ClassInfo::from($absolutePath))); /** @phpstan-ignore-line */
        }

        return $isReadable;
    }

    private function triggerEvent(GacelaEventInterface $event): void
    {
        if (self::$listeners === []) {
            self::$listeners = Config::getInstance()
                ->getSetupGacela()
                ->getEventListeners();
        }

        foreach (self::$listeners[GacelaConfigReaderListener::class] ?? [] as $callable) {
            $callable($event);
        }
    }
}
