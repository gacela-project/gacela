<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

use function array_values;
use function file_get_contents;
use function filemtime;
use function filesize;
use function is_array;
use function is_file;
use function json_decode;
use function sprintf;

/**
 * The one reader of `vendor/composer/installed.json`.
 *
 * Two things read that file -- package discovery at bootstrap, and the `doctor`
 * check that reports on what packages declared -- and a second parser of the
 * same file is how the two drift apart. It lives below the console because
 * discovery runs during `Gacela::bootstrap()`, where nothing of the console is
 * loaded.
 *
 * Absent, unreadable and malformed all collapse to null on purpose: this is a
 * manifest nobody here owns, and for every caller the three are the same
 * outcome -- there is nothing to read, so nothing is discovered.
 *
 * `installed.json` rather than `composer.lock`: the lock file says what should
 * be installed, and this one says what is, which is what `--no-dev` leaves
 * behind and what the running application actually has on disk.
 */
final class InstalledPackagesReader
{
    public function __construct(
        private readonly string $appRootDir,
    ) {
    }

    /**
     * Where Composer writes it.
     */
    public function path(): string
    {
        return $this->appRootDir
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'composer'
            . DIRECTORY_SEPARATOR . 'installed.json';
    }

    /**
     * Every installed package, as Composer recorded it.
     *
     * @return list<mixed>|null
     */
    public function read(): ?array
    {
        $path = $this->path();

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            return null;
        }

        /** @var mixed $packages */
        $packages = $decoded['packages'] ?? $decoded;

        // Keys dropped rather than preserved: every caller iterates, and
        // Composer 1 wrote the list at the top level where Composer 2 writes it
        // under `packages`.
        return is_array($packages) ? array_values($packages) : null;
    }

    /**
     * What the file is right now, cheap enough to ask on every boot.
     *
     * Size and modification time, not a hash of the contents: hashing means
     * reading the whole file, and not reading it is the entire purpose of the
     * cache this keys. A `composer install` rewrites the file, so both halves
     * move together in practice -- but a reinstall of the same package set
     * leaves the size alone, and swapping one package for another of the same
     * name length leaves nothing but the size to notice.
     *
     * Null when there is no file, which is the same answer `read()` gives: a
     * caller that gets null here has nothing to key a cache on and nothing to
     * discover either.
     */
    public function fingerprint(): ?string
    {
        $path = $this->path();

        if (!is_file($path)) {
            return null;
        }

        $modifiedAt = filemtime($path);
        $size = filesize($path);

        if ($modifiedAt === false || $size === false) {
            return null;
        }

        return sprintf('%d-%d', $modifiedAt, $size);
    }
}
