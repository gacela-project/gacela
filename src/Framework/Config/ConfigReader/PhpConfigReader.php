<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\ConfigReader;

use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Event\ConfigReader\ReadPhpConfigEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use JsonSerializable;
use RuntimeException;

use function is_array;
use function sprintf;

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

        if (self::shouldDispatch(ReadPhpConfigEvent::class)) {
            self::dispatchEvent(new ReadPhpConfigEvent($absolutePath));
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
            // Named, because the glob decides which files land here: a project
            // with five config files got the same sentence whichever one was
            // wrong, and a PHP file that is not a config at all -- dropped into
            // `config/` where `config/*.php` matches it -- returns 1 from
            // `include` and arrives here looking identical to a typo.
            throw new RuntimeException(sprintf(
                'The PHP config file "%s" must return an array or a JsonSerializable object!',
                $absolutePath,
            ));
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
