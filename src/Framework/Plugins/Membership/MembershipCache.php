<?php

declare(strict_types=1);

namespace Gacela\Framework\Plugins\Membership;

use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;

use function is_file;
use function serialize;
use function sha1;

/**
 * The `#[Plugin]`, `#[Tag]` and `#[AsListener]` members `cache:warm --attributes` found, so a warmed
 * application reads one file instead of walking its module paths.
 *
 * @psalm-import-type MembersRows from Members
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
     * namespaces, and in these packages.
     *
     * @param list<string> $appModulePaths
     * @param list<string> $projectNamespaces
     * @param array<string, list<string>> $packageSources
     */
    public static function forScan(string $cacheDir, string $appRootDir, array $appModulePaths, array $projectNamespaces, array $packageSources = []): self
    {
        return new self($cacheDir, $appRootDir, self::fingerprintOf($appModulePaths, $projectNamespaces, $packageSources));
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
         * @var MembersRows $content
         */
        $content = require $this->path();

        return Members::fromRows($content);
    }

    /**
     * Refuses a scan with problems: the file carries none, so reading it would
     * drop the broken listener without a word.
     */
    public function write(Members $members): bool
    {
        if ($members->problems !== []) {
            return false;
        }

        return FileCache::writeAtomically($this->path(), $members->toRows());
    }

    /**
     * @param list<string> $appModulePaths
     * @param list<string> $projectNamespaces
     * @param array<string, list<string>> $packageSources
     */
    private static function fingerprintOf(array $appModulePaths, array $projectNamespaces, array $packageSources): string
    {
        return sha1(serialize([$appModulePaths, $projectNamespaces, $packageSources]));
    }
}
