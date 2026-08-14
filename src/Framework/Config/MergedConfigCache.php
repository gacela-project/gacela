<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Cache\FileCache;

use function implode;
use function sha1;
use function strlen;
use function substr;

final class MergedConfigCache
{
    public const FILENAME_PREFIX = 'gacela-merged-config';

    public const FILENAME_EXTENSION = '.php';

    /**
     * @param list<string> $dimensions the resolved values selecting this configuration, beyond the env
     */
    public function __construct(
        private readonly string $cacheDir,
        private readonly string $env = '',
        private readonly string $appRootDir = '',
        private readonly array $dimensions = [],
    ) {
    }

    public function exists(): bool
    {
        return is_file($this->filename());
    }

    /**
     * @return array<string,mixed>
     */
    public function load(): array
    {
        /**
         * @psalm-suppress UnresolvableInclude
         *
         * @var array<string,mixed> $data
         */
        $data = require $this->filename();

        return $data;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function write(array $data): void
    {
        FileCache::writeAtomically($this->filename(), $data);
    }

    public function clear(): void
    {
        FileCache::delete($this->filename());

        // Every other dimension tuple of this application too. Clearing only
        // the tuple this process happens to be leaves the other regions'
        // answers on disk, and the next deploy of one of them reads its own
        // stale file back. Anchored to this app's hash, so another
        // application sharing the cache dir is not touched.
        foreach ($this->siblingTupleFilenames() as $filename) {
            FileCache::delete($filename);
        }

        // Also drop a cache written before filenames were app-scoped, so
        // clearing leaves no stale pre-#465 file behind in a shared dir.
        $legacyFilename = $this->buildFilename('');
        if ($legacyFilename !== $this->filename()) {
            FileCache::delete($legacyFilename);
        }
    }

    /**
     * The cache dir can be shared between apps (it defaults to the system
     * temp dir), so the filename embeds a hash of the app root: without it,
     * every app using the shared default read and wrote the same file and
     * silently served another app's merged config.
     */
    public function filename(): string
    {
        $appSuffix = $this->appRootDir !== ''
            ? '-' . substr(sha1($this->appRootDir), 0, 12)
            : '';

        return $this->buildFilename($appSuffix);
    }

    private function buildFilename(string $appSuffix): string
    {
        $envSuffix = $this->env !== '' ? '-' . $this->env : '';

        return $this->cacheDir
            . DIRECTORY_SEPARATOR
            . self::FILENAME_PREFIX
            . $appSuffix
            . $envSuffix
            . $this->dimensionSuffix()
            . self::FILENAME_EXTENSION;
    }

    /**
     * The dimension tuples of this app and env, whichever values they carry.
     *
     * @return list<string>
     */
    private function siblingTupleFilenames(): array
    {
        if ($this->appRootDir === '') {
            return [];
        }

        $withoutExtension = substr($this->buildFilename(
            '-' . substr(sha1($this->appRootDir), 0, 12),
        ), 0, -strlen(self::FILENAME_EXTENSION));

        // One dimension segment past the env, which is the only shape a tuple
        // filename takes.
        $stem = $this->dimensions === []
            ? $withoutExtension
            : substr($withoutExtension, 0, -strlen($this->dimensionSuffix()));

        return glob($stem . '-*' . self::FILENAME_EXTENSION) ?: [];
    }

    /**
     * Hashed rather than spelled out, and absent entirely when nothing is
     * declared.
     *
     * Absent, because a project that declares no dimension must keep the
     * filename it already has: spelling one out would invalidate every warm
     * cache on upgrade for a feature the project does not use.
     *
     * Hashed, because further readable segments cannot be told apart from an
     * env that contains the separator. `-prod-eu` is either the env `prod-eu`
     * with no dimensions or the env `prod` in region `eu`, and hyphenated env
     * names are ordinary -- so the two configurations would share one file and
     * silently serve each other. The same reason the class-name cache carries
     * an opaque bootstrap fingerprint instead of a readable one.
     */

    private function dimensionSuffix(): string
    {
        if ($this->dimensions === []) {
            return '';
        }

        return '-' . substr(sha1(implode("\0", $this->dimensions)), 0, 12);
    }
}
