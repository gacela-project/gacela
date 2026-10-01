<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Closure;
use Gacela\Framework\Cache\FileCache;

use function explode;
use function implode;
use function is_array;
use function is_string;
use function sha1;
use function strlen;
use function substr;

final class MergedConfigCache
{
    public const FILENAME_PREFIX = 'gacela-merged-config';

    public const FILENAME_EXTENSION = '.php';

    /**
     * The key of one string saying what the values answer for: the kind, the
     * declaration signature and, for a verified file, the packed source stamps.
     * One string rather than three entries, because where OPcache is off, as
     * in most CLI runs, the file is compiled on every hit and a string literal
     * compiles far faster than an array. A file without it was written before
     * sources were recorded, and is never current.
     */
    private const HEADER = 'gacela-merged-config';

    /** Written by `cache:warm`, a deploy step: served until the next warm or `cache:clear`. */
    private const TRUSTED = 'trusted';

    /** Written on a miss: served while what it was read from is unchanged. */
    private const VERIFIED = 'verified';

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
     * The cached values, whatever they were read from. For reports and tools;
     * the bootstrap asks {@see loadIfCurrent()}.
     *
     * @return array<string,mixed>
     */
    public function load(): array
    {
        $data = $this->read();

        return $this->entryOf($data)['values'] ?? $data;
    }

    /**
     * The cached values, when they still answer for this configuration. A
     * trusted file always does. A verified one does while the declarations are
     * the same and none of its sources changed; the signature is asked for
     * only then, so a trusted hit costs no more than reading the file.
     *
     * @param Closure():string $declarationSignature
     *
     * @return array<string,mixed>|null
     */
    public function loadIfCurrent(Closure $declarationSignature): ?array
    {
        $entry = $this->entryOf($this->read());

        if ($entry === null) {
            return null;
        }

        if ($entry['kind'] === self::VERIFIED
            && ($entry['declared'] !== $declarationSignature()
                || !ConfigSourceStamps::areCurrent(ConfigSourceStamps::unpack($entry['sources'])))
        ) {
            return null;
        }

        return $entry['values'];
    }

    /**
     * For `cache:warm`. A deploy artifact, so the bootstrap checks neither the
     * declarations nor any source against it, and a hit costs what it did
     * before sources were recorded.
     *
     * @param array<string,mixed> $values
     */
    public function writeTrusted(array $values, string $declarationSignature): void
    {
        $this->write(self::TRUSTED, $declarationSignature, '', $values);
    }

    /**
     * For a miss, so an edited, added or removed config file rebuilds it.
     *
     * @param array<string,mixed> $values
     * @param array<string,string> $sources see {@see ConfigSourceStamps}
     */
    public function writeVerified(array $values, string $declarationSignature, array $sources): void
    {
        $this->write(self::VERIFIED, $declarationSignature, ConfigSourceStamps::pack($sources), $values);
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

    /**
     * @return array<string,mixed>
     */
    private function read(): array
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
     * @param array<string,mixed> $values
     */
    private function write(string $kind, string $declarationSignature, string $sources, array $values): void
    {
        FileCache::writeAtomically($this->filename(), [
            self::HEADER => $kind . "\n" . $declarationSignature . "\n" . $sources,
            'values' => $values,
        ]);
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array{kind: string, declared: string, sources: string, values: array<string,mixed>}|null
     */
    private function entryOf(array $data): ?array
    {
        $header = $data[self::HEADER] ?? null;
        $values = $data['values'] ?? null;

        if (!is_string($header) || !is_array($values)) {
            return null;
        }

        [$kind, $declared, $sources] = explode("\n", $header, 3) + ['', '', ''];

        if ($kind !== self::TRUSTED && $kind !== self::VERIFIED) {
            return null;
        }

        /** @var array<string,mixed> $values */
        return ['kind' => $kind, 'declared' => $declared, 'sources' => $sources, 'values' => $values];
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
