<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;

use function array_map;
use function is_file;

/**
 * The `#[Plugin]` members `cache:warm --attributes` found, so a warmed
 * application reads one file instead of walking its module paths.
 */
final class MembershipCache
{
    public const FILENAME = 'gacela-membership.php';

    public function __construct(
        private readonly string $cacheDir,
        private readonly string $appRootDir,
    ) {
    }

    public function path(): string
    {
        return AbstractPhpFileCache::absoluteFilename($this->cacheDir, self::FILENAME, $this->appRootDir);
    }

    public function isWarm(): bool
    {
        return is_file($this->path());
    }

    /**
     * @return list<PluginMember>|null null when the cache was never warmed
     */
    public function read(): ?array
    {
        if (!$this->isWarm()) {
            return null;
        }

        /**
         * @psalm-suppress UnresolvableInclude
         *
         * @var array{plugins: list<array{0: class-string, 1: class-string, 2: int}>} $content
         */
        $content = require $this->path();

        return array_map(PluginMember::fromRow(...), $content['plugins']);
    }

    /**
     * @param list<PluginMember> $plugins
     */
    public function write(array $plugins): bool
    {
        return FileCache::writeAtomically($this->path(), [
            'plugins' => array_map(static fn (PluginMember $member): array => $member->toRow(), $plugins),
        ]);
    }
}
