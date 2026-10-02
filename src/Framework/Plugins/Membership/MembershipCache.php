<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;

use function is_file;
use function serialize;
use function sha1;

/**
 * The `#[Plugin]` and `#[Tag]` members `cache:warm --attributes` found, so a warmed
 * application reads one file instead of walking its module paths.
 */
final class MembershipCache
{
    public const FILENAME = 'gacela-membership.php';

    /**
     * @param string $fingerprint see {@see fingerprintOf()}: two entrypoints of one
     *                            application that scan different paths or namespaces
     *                            each read and write their own file
     */
    public function __construct(
        private readonly string $cacheDir,
        private readonly string $appRootDir,
        private readonly string $fingerprint = '',
    ) {
    }

    /**
     * The file for one scan: the members found under these module paths and
     * namespaces.
     *
     * @param list<string> $appModulePaths
     * @param list<string> $projectNamespaces
     */
    public static function forScan(string $cacheDir, string $appRootDir, array $appModulePaths, array $projectNamespaces): self
    {
        return new self($cacheDir, $appRootDir, self::fingerprintOf($appModulePaths, $projectNamespaces));
    }

    public function path(): string
    {
        return AbstractPhpFileCache::absoluteFilename($this->cacheDir, self::FILENAME, $this->appRootDir, $this->fingerprint);
    }

    public function isWarm(): bool
    {
        return is_file($this->path());
    }

    /**
     * @return Members|null null when the cache was never warmed
     */
    public function read(): ?Members
    {
        if (!$this->isWarm()) {
            return null;
        }

        /**
         * @psalm-suppress UnresolvableInclude
         *
         * @var array{plugins: list<array{0: class-string, 1: class-string, 2: int}>, tags?: list<array{0: string, 1: class-string}>} $content
         */
        $content = require $this->path();

        return Members::fromRows($content);
    }

    public function write(Members $members): bool
    {
        return FileCache::writeAtomically($this->path(), $members->toRows());
    }

    /**
     * @param list<string> $appModulePaths
     * @param list<string> $projectNamespaces
     */
    private static function fingerprintOf(array $appModulePaths, array $projectNamespaces): string
    {
        return sha1(serialize([$appModulePaths, $projectNamespaces]));
    }
}
