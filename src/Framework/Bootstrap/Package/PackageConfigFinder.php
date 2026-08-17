<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

use function array_pop;
use function dirname;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function preg_match;
use function str_replace;
use function str_starts_with;

/**
 * Which installed packages declare a Gacela config, and where that file is.
 *
 * Only packages carrying the key are considered. `vendor/` is never scanned for
 * `gacela.php` files: that discovery has been measured in this repository and
 * rejected -- it costs a directory walk of every installed package on every
 * boot to answer a question one already-open file answers.
 */
final class PackageConfigFinder
{
    private const string EXTRA_KEY = 'gacela';

    private const string CONFIG_KEY = 'config';

    public function __construct(
        private readonly InstalledPackagesReader $reader,
    ) {
    }

    /**
     * In Composer's installed order, which is the merge order.
     *
     * @return list<PackageConfigDeclaration>
     */
    public function find(): array
    {
        $installed = $this->reader->read();

        if ($installed === null) {
            return [];
        }

        // Composer records `install-path` relative to this directory.
        $vendorComposerDir = dirname($this->reader->path());

        $declarations = [];

        foreach ($installed as $package) {
            if (!is_array($package)) {
                continue;
            }

            $declaration = $this->declarationOf($package, $vendorComposerDir);

            if ($declaration instanceof PackageConfigDeclaration) {
                $declarations[] = $declaration;
            }
        }

        return $declarations;
    }

    /**
     * @param array<array-key, mixed> $package
     */
    private function declarationOf(array $package, string $vendorComposerDir): ?PackageConfigDeclaration
    {
        $name = $package['name'] ?? null;

        if (!is_string($name) || $name === '') {
            return null;
        }

        $extra = $package['extra'] ?? null;

        if (!is_array($extra)) {
            return null;
        }

        $gacela = $extra[self::EXTRA_KEY] ?? null;

        if (!is_array($gacela)) {
            return null;
        }

        $declaredPath = $gacela[self::CONFIG_KEY] ?? null;

        if (!is_string($declaredPath) || $declaredPath === '') {
            return null;
        }

        return new PackageConfigDeclaration(
            $name,
            $declaredPath,
            $this->resolve($package, $name, $vendorComposerDir, $declaredPath),
        );
    }

    /**
     * @param array<array-key, mixed> $package
     */
    private function resolve(array $package, string $name, string $vendorComposerDir, string $declaredPath): string
    {
        /** @var mixed $installPath */
        $installPath = $package['install-path'] ?? null;

        // Composer 2 records it; Composer 1 did not, and a package installed
        // where its name says it is needs no record for this to be right.
        $packageDir = is_string($installPath) && $installPath !== ''
            ? $this->absolutize($installPath, $vendorComposerDir)
            : dirname($vendorComposerDir) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);

        return $this->normalize($packageDir . DIRECTORY_SEPARATOR . $declaredPath);
    }

    private function absolutize(string $path, string $vendorComposerDir): string
    {
        return $this->isAbsolute($path)
            ? $path
            : $vendorComposerDir . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * A unix root, a windows drive, or a UNC share.
     */
    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    /**
     * Fold the separators and resolve `.` and `..` textually.
     *
     * Not `realpath()`: that answers false for a path that does not exist,
     * which is exactly the declaration `doctor` has to be able to report, and
     * it resolves symlinks -- so a package installed from a path repository
     * would be named by wherever it really lives rather than by where the
     * application's own `vendor/` says it is.
     */
    private function normalize(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Whatever is in front of the first segment and is not one: a windows
        // drive, a root, both, or neither.
        $prefix = '';

        if (preg_match('#^[A-Za-z]:#', $path) === 1) {
            $prefix = substr($path, 0, 2);
            $path = substr($path, 2);
        }

        // Not trimmed off the path: the loop below drops every empty segment,
        // which is what a leading -- or doubled -- separator becomes.
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $prefix .= DIRECTORY_SEPARATOR;
        }

        $segments = [];

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            if ($segment === '.') {
                continue;
            }

            // A `..` with nothing above it stays: dropping it would silently
            // turn a path pointing outside the vendor directory into one inside
            // it, and name a file the package never declared.
            $last = $segments === [] ? null : $segments[array_key_last($segments)];

            if ($segment === '..' && $last !== null && $last !== '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return $prefix . implode(DIRECTORY_SEPARATOR, $segments);
    }
}
