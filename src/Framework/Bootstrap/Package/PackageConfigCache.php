<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

use Gacela\Framework\Cache\FileCache;

use function array_map;
use function is_array;
use function is_file;
use function is_string;

/**
 * Which packages declare a Gacela config, remembered between boots.
 *
 * `installed.json` is a few hundred kilobytes of JSON in a real application, and
 * decoding it to find out that no package declares anything is the cost this
 * cache exists to stop paying. The key is the file's own fingerprint, so a
 * `composer install` invalidates it and nothing else has to.
 *
 * What is cached is the *declarations*, before any opt-out is applied. A project
 * editing `dontDiscover()` must not have to clear a cache for the edit to take
 * effect, and filtering a list of a handful of entries costs nothing.
 */
final class PackageConfigCache
{
    public const string FILENAME = 'gacela-discovered-packages.php';

    public function __construct(
        private readonly string $cacheDir,
    ) {
    }

    public function path(): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . self::FILENAME;
    }

    /**
     * The declarations this fingerprint was cached with, or null when there is
     * no usable entry for it.
     *
     * A cache written for a different fingerprint is not stale data to be
     * repaired -- it is an answer to a different question, and it is discarded
     * without a word.
     *
     * @return list<PackageConfigDeclaration>|null
     */
    public function read(string $fingerprint): ?array
    {
        $path = $this->path();

        if (!is_file($path)) {
            return null;
        }

        /**
         * Written by this class, through {@see FileCache::writeAtomically}.
         *
         * @var mixed $cached
         */
        $cached = require $path;

        if (!is_array($cached)) {
            return null;
        }

        $cachedFingerprint = $cached['fingerprint'] ?? null;

        if (!is_string($cachedFingerprint) || $cachedFingerprint !== $fingerprint) {
            return null;
        }

        $packages = $cached['packages'] ?? null;

        if (!is_array($packages)) {
            return null;
        }

        $declarations = [];

        foreach ($packages as $row) {
            if (!is_array($row)) {
                return null;
            }

            $declaration = PackageConfigDeclaration::fromArray($row);

            // One unreadable row makes the whole entry untrustworthy: a
            // partially-read cache would boot an application with some of its
            // packages, which is worse than reading `installed.json` again.
            if (!$declaration instanceof PackageConfigDeclaration) {
                return null;
            }

            $declarations[] = $declaration;
        }

        return $declarations;
    }

    /**
     * @param list<PackageConfigDeclaration> $declarations
     */
    public function write(string $fingerprint, array $declarations): void
    {
        FileCache::writeAtomically($this->path(), [
            'fingerprint' => $fingerprint,
            'packages' => array_map(
                static fn (PackageConfigDeclaration $declaration): array => $declaration->toArray(),
                $declarations,
            ),
        ]);
    }
}
