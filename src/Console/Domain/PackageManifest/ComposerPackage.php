<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\PackageManifest;

use function array_keys;
use function in_array;
use function is_array;
use function is_string;

/**
 * One `composer.json` the repository owns, reduced to what the check reads.
 *
 * `suggest` sits beside `require` here rather than being ignored, because the
 * two together are what a package promises: `require` is "you get this",
 * `suggest` is "install it yourself if you want that part". An import covered
 * by either is declared.
 */
final class ComposerPackage
{
    /**
     * @param array<string, string> $autoloadPrefixes psr-4/psr-0 prefix => relative directory
     * @param list<string> $required package names in `require`
     * @param list<string> $requiredForDev package names in `require-dev`
     * @param list<string> $suggested package names in `suggest`
     */
    private function __construct(
        public readonly string $name,
        public readonly string $manifestPath,
        public readonly string $rootDir,
        public readonly array $autoloadPrefixes,
        public readonly array $required,
        public readonly array $requiredForDev,
        public readonly array $suggested,
    ) {
    }

    /**
     * @param array<array-key, mixed> $decoded
     */
    public static function fromDecodedJson(array $decoded, string $manifestPath, string $rootDir): ?self
    {
        $name = $decoded['name'] ?? null;

        // A manifest without a name cannot be published, so it has no standalone
        // install to break -- there is nothing for this check to be about.
        if (!is_string($name) || $name === '') {
            return null;
        }

        return new self(
            name: $name,
            manifestPath: $manifestPath,
            rootDir: $rootDir,
            autoloadPrefixes: self::prefixesOf($decoded['autoload'] ?? null),
            required: self::keysOf($decoded['require'] ?? null),
            requiredForDev: self::keysOf($decoded['require-dev'] ?? null),
            suggested: self::keysOf($decoded['suggest'] ?? null),
        );
    }

    /**
     * Whether the manifest accounts for the given package at all.
     *
     * Every section counts, `require-dev` included. That is deliberately weaker
     * than "would a consumer receive it", and it is the strongest claim this
     * check can make honestly: a package distributed as a phar declares no
     * autoload prefix -- phpstan/phpstan is the example in this repository --
     * so its classes get attributed to whichever sibling happens to publish the
     * namespace. Demanding a *specific* section would then tell a reader to add
     * a requirement on the wrong package.
     *
     * Total absence is unambiguous, and it is the failure this check exists
     * for: an import mentioned in no section is the one that fatals the day the
     * package is installed on its own.
     */
    public function declares(string $packageName): bool
    {
        return in_array($packageName, $this->required, true)
            || in_array($packageName, $this->requiredForDev, true)
            || in_array($packageName, $this->suggested, true);
    }

    /**
     * `autoload-dev` is deliberately absent: it is not installed for a consumer,
     * so an import reachable only from it cannot break a standalone install.
     *
     * @return array<string, string>
     */
    private static function prefixesOf(mixed $autoload): array
    {
        if (!is_array($autoload)) {
            return [];
        }

        $prefixes = [];

        foreach (['psr-4', 'psr-0'] as $standard) {
            $section = $autoload[$standard] ?? null;
            if (!is_array($section)) {
                continue;
            }

            /** @var mixed $directory */
            foreach ($section as $prefix => $directory) {
                if (is_string($prefix) && $prefix !== '') {
                    // A prefix may map to several directories; only the prefix
                    // matters here, so the first spelling of it is enough.
                    $prefixes[$prefix] ??= is_string($directory) ? $directory : '';
                }
            }
        }

        return $prefixes;
    }

    /**
     * @return list<string>
     */
    private static function keysOf(mixed $section): array
    {
        if (!is_array($section)) {
            return [];
        }

        $names = [];

        foreach (array_keys($section) as $name) {
            if (is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
