<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use function basename;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

/**
 * A config file that refines another file the same base pattern matched.
 *
 * `addAppConfig('config/*.php')` -- the pattern `bin/gacela init` scaffolds --
 * is globbed literally, so it also matches the environment files the framework
 * itself names: `app-prod.php`, `app-prod-eu.php`. It matched them as part of
 * the *base* layer, before the environment-and-dimensions chain was applied on
 * top, so every environment's file was read on every run. A key with a value in
 * the base layer came out right by accident of `glob()` ordering -- `-` sorts
 * before `.`, so `app.php` was merged last -- and a key only an environment file
 * set had nothing to overwrite it, so a developer silently read the production
 * value (#889).
 *
 * The rule below is the framework's own naming scheme, read backwards: strip one
 * or more trailing `-<segment>` parts from a basename, and if that yields
 * another file the same pattern matched, this file is a layer of that one and
 * belongs to its environment rather than to the base. It is the scheme
 * {@see PathNormalizer\WithSuffixAbsolutePathStrategy} generates, down to
 * putting the suffix before the *first* dot, so a file is stripped exactly the
 * way it would have been built.
 *
 * The declared alphabet cannot answer this instead: `APP_ENV` is read from the
 * environment and is never declared anywhere, so during a developer's run there
 * is nothing that knows `prod` is an environment name -- which is precisely the
 * run that has to exclude `app-prod.php`.
 *
 * What the rule cannot know is intent. A project with `config/app.php` and a
 * genuinely unrelated `config/app-extra.php` has the second one excluded from
 * its base layer, and read only when the chain resolves to `extra`. That is why
 * `doctor` names every file excluded this way: the exclusion trades a silent
 * wrong value for a reported non-load, never for a silent one.
 *
 * @internal
 */
final class EnvironmentLayer
{
    private function __construct(
        public readonly string $path,
        public readonly string $basePath,
        public readonly string $suffix,
    ) {
    }

    /**
     * Which of these files are environment layers of another one among them.
     *
     * Only ever a *subset*: a file is a layer of something with a strictly
     * shorter basename, so the shortest of any set can never be one and a
     * pattern that matched something always keeps at least one base file. That
     * is what stops the exclusion turning "read the wrong file" into "read
     * nothing".
     *
     * @param list<string> $paths every file one base pattern matched
     *
     * @return array<string,self> keyed by the layer's own path
     */
    public static function within(array $paths): array
    {
        $matched = [];
        foreach ($paths as $path) {
            $matched[$path] = true;
        }

        $layers = [];
        foreach ($paths as $path) {
            $layer = self::of($path, $matched);

            if ($layer instanceof self) {
                $layers[$path] = $layer;
            }
        }

        return $layers;
    }

    /**
     * @param array<string,true> $matched
     */
    private static function of(string $path, array $matched): ?self
    {
        $basename = basename($path);
        $directory = substr($path, 0, strlen($path) - strlen($basename));

        // Before the first dot, which is where the suffix would have been
        // inserted: `default.app.php` is suffixed as `default-prod.app.php`.
        $dot = strpos($basename, '.');
        $name = $dot === false ? $basename : substr($basename, 0, $dot);
        $extension = $dot === false ? '' : substr($basename, $dot);

        $found = null;
        $candidate = $name;

        // A dash at position 0 is not a suffix separator: stripping it would
        // leave no name at all, and `-prod.php` refines nothing.
        while (($dash = strrpos($candidate, '-')) !== false && $dash > 0) {
            $candidate = substr($candidate, 0, $dash);
            $sibling = $directory . $candidate . $extension;

            if (isset($matched[$sibling])) {
                // Kept rather than returned: with both `app-prod.php` and
                // `app.php` beside it, `app-prod-eu.php` is a layer of the base
                // file, and its suffix is the whole `prod-eu` that the
                // environment-and-dimensions chain has to resolve to. Stopping
                // at the first hit would report the middle link and a suffix of
                // `eu`, which no chain ever resolves to on its own.
                $found = new self($path, $sibling, substr($name, strlen($candidate) + 1));
            }
        }

        return $found;
    }
}
