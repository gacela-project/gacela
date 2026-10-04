<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Closure;
use Error;
use Gacela\Framework\Cache\FileCache;
use stdClass;
use UnitEnum;

use function explode;
use function get_object_vars;
use function implode;
use function is_array;
use function is_object;
use function is_resource;
use function is_string;
use function method_exists;
use function sha1;
use function strlen;
use function substr;

final class MergedConfigCache
{
    public const FILENAME_PREFIX = 'gacela-merged-config';

    public const FILENAME_EXTENSION = '.php';

    /**
     * In the name of every file this version writes. A file without it was
     * written before sources were recorded: it may hold values from before an
     * edit, so it is never read, and `clear()` still removes it.
     */
    private const VERSION = '-v2';

    /**
     * Present only in a file written on a miss, holding the declaration
     * signature and the packed source stamps as one string, so the file
     * compiles as the values plus one literal. A NUL byte keeps it from ever
     * being a configuration key. A file without it was written by
     * `cache:warm` and is exactly the values, so a deployed hit costs one
     * `require`, as it did before sources were recorded.
     */
    private const HEADER = "\0gacela-merged-config-sources";

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
        $data = $this->read() ?? [];

        if (!isset($data[self::HEADER])) {
            return $data;
        }

        $values = $data['values'] ?? null;

        if (!is_array($values)) {
            return [];
        }

        /** @var array<string,mixed> $values */
        return $values;
    }

    /**
     * The cached values, when they still answer for this configuration. A file
     * `cache:warm` wrote always does. One written on a miss does while the
     * declarations are the same and none of its sources changed; the signature
     * is asked for only then.
     *
     * @param Closure():string $declarationSignature
     *
     * @return array<string,mixed>|null
     */
    public function loadIfCurrent(Closure $declarationSignature): ?array
    {
        $data = $this->read();
        if ($data === null) {
            return null;
        }

        $header = $data[self::HEADER] ?? null;

        if ($header === null) {
            return $data;
        }

        $values = $data['values'] ?? null;

        if (!is_string($header) || !is_array($values)) {
            return null;
        }

        [$declared, $sources] = explode("\n", $header, 2) + ['', ''];

        if ($declared !== $declarationSignature()
            || !ConfigSourceStamps::areCurrent(ConfigSourceStamps::unpack($sources))
        ) {
            return null;
        }

        /** @var array<string,mixed> $values */
        return $values;
    }

    /**
     * For `cache:warm`. A deploy artifact: the bootstrap checks neither the
     * declarations nor any source against it until the next warm or
     * `cache:clear`.
     *
     * @param array<string,mixed> $values
     */
    public function writeTrusted(array $values): void
    {
        $this->write($values, $values);
    }

    /**
     * For a miss, so an edited, added or removed config file rebuilds it.
     *
     * @param array<string,mixed> $values
     * @param array<string,string> $sources see {@see ConfigSourceStamps}
     */
    public function writeVerified(array $values, string $declarationSignature, array $sources): void
    {
        $this->write($values, [
            self::HEADER => $declarationSignature . "\n" . ConfigSourceStamps::pack($sources),
            'values' => $values,
        ]);
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
        // clearing leaves no stale pre-#465 file behind in a shared dir, and
        // every file written before sources were recorded.
        foreach (['', self::VERSION] as $version) {
            foreach ($this->appSuffix() === '' ? [''] : ['', $this->appSuffix()] as $appSuffix) {
                $filename = $this->buildFilename($appSuffix, $version);
                if ($filename !== $this->filename()) {
                    FileCache::delete($filename);
                }
            }
        }

        foreach ($this->siblingTupleFilenames('') as $filename) {
            FileCache::delete($filename);
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
        return $this->buildFilename($this->appSuffix(), self::VERSION);
    }

    /**
     * Null for a file that cannot be loaded, such as one an older version
     * wrote with a closure in it: a miss, so the next write replaces it.
     *
     * @return array<string,mixed>|null
     */
    private function read(): ?array
    {
        try {
            /**
             * @psalm-suppress UnresolvableInclude
             *
             * @var array<string,mixed> $data
             */
            $data = require $this->filename();
        } catch (Error) {
            return null;
        }

        return $data;
    }

    private function appSuffix(): string
    {
        return $this->appRootDir !== '' ? '-' . substr(sha1($this->appRootDir), 0, 12) : '';
    }

    private function buildFilename(string $appSuffix, string $version): string
    {
        $envSuffix = $this->env !== '' ? '-' . $this->env : '';

        return $this->cacheDir
            . DIRECTORY_SEPARATOR
            . self::FILENAME_PREFIX
            . $version
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
    private function siblingTupleFilenames(string $version = self::VERSION): array
    {
        if ($this->appRootDir === '') {
            return [];
        }

        $withoutExtension = substr($this->buildFilename($this->appSuffix(), $version), 0, -strlen(self::FILENAME_EXTENSION));

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

    /**
     * A closure, or an object without `__set_state()`, exports as code that
     * fails when the file is read, so such values are not cached at all, and
     * an older file for this configuration goes too rather than answer for it.
     *
     * @param array<string,mixed> $values
     * @param array<string,mixed> $contents
     */
    private function write(array $values, array $contents): void
    {
        if (!self::isExportable($values)) {
            FileCache::delete($this->filename());

            return;
        }

        FileCache::writeAtomically($this->filename(), $contents);
    }

    private static function isExportable(mixed $value): bool
    {
        if (is_array($value)) {
            /** @var mixed $item */
            foreach ($value as $item) {
                if (!self::isExportable($item)) {
                    return false;
                }
            }

            return true;
        }

        if (!is_object($value)) {
            return !is_resource($value);
        }

        if ($value instanceof stdClass) {
            return self::isExportable(get_object_vars($value));
        }

        return $value instanceof UnitEnum || method_exists($value, '__set_state');
    }
}
