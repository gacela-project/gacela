<?php

declare(strict_types=1);

namespace Gacela\Framework\Preload;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

use function class_exists;
use function dirname;
use function interface_exists;
use function is_dir;
use function is_file;
use function sort;
use function spl_autoload_register;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trait_exists;

/**
 * What `opcache.preload` loads, and how it manages to link.
 *
 * Preloading only pays off when a class is *linked*, not merely compiled. PHP
 * links what was preloaded once the script finishes, and succeeds only if every
 * parent, interface and trait came along too. Anything still missing a
 * dependency is reported at startup and dropped from the image altogether.
 *
 * That is why the set is derived here instead of hand-listed. A list gets this
 * wrong by construction: naming `AbstractFacade` without `CacheableTrait`, or
 * `Container` without `ContainerInterface`, preloads neither of them -- and the
 * list also rots, since a renamed file only ever shows up as a log line.
 *
 * Linking is driven through an autoloader rather than by compiling the files,
 * because asking for a class pulls in whatever it extends, implements or uses
 * *wherever that lives* -- including the packages outside this tree, which a
 * directory scan would otherwise have to name for itself.
 * Composer's autoloader is deliberately not the one used: it runs every
 * installed package's `files` entries, and the preload context forbids the I/O
 * some of them do at load time -- one `fopen('php://stdout')` in a stream
 * library aborts the entire preload. Gacela's runtime closure is a handful of
 * packages, so those prefixes are named in {@see autoloadPrefixes()} instead.
 */
final class Preloader
{
    /**
     * Requires phpunit, which is a dev dependency and absent in production.
     * Loading it would raise a fatal in a context that cannot report one
     * usefully.
     *
     * Matched on the namespace rather than on the path, so it names exactly the
     * one package it means and not any directory that happens to be called
     * something similar.
     */
    private const EXCLUDED_NAMESPACE = 'Gacela\\Framework\\Testing\\';

    /**
     * Link every framework class into the preload image.
     *
     * @param string $gacelaRoot the package root: the directory holding `src/`
     */
    public static function run(string $gacelaRoot): PreloadResult
    {
        self::registerAutoloader(self::autoloadPrefixes($gacelaRoot));

        $linked = [];
        $skipped = [];

        foreach (self::classNames($gacelaRoot) as $className) {
            try {
                if (class_exists($className) || interface_exists($className) || trait_exists($className)) {
                    $linked[] = $className;
                } else {
                    $skipped[] = $className;
                }
            } catch (Throwable $throwable) {
                // One class that cannot link must not cost the whole image.
                // A dev-only dependency appearing under src/ later lands here
                // and is reported, rather than aborting every other class.
                // Spelled with sprintf like the summary that renders it, and
                // not asserted character by character: the reason is PHP's own
                // wording, which has changed between versions before.
                $skipped[] = sprintf('%s (%s)', $className, $throwable->getMessage());
            }
        }

        return new PreloadResult($linked, $skipped);
    }

    /**
     * Every class of the runtime closure, in a stable order.
     *
     * All three packages, not only the framework: the container is reached on
     * the first `Container::withConfig()` and cost more than everything else in
     * bootstrap put together while it was merely *linkable* rather than
     * preloaded. Linking pulls in whatever a framework class extends or
     * implements, which is a handful of container classes -- the rest were
     * still being read off disk on the first request.
     *
     * @return list<class-string>
     */
    public static function classNames(string $gacelaRoot): array
    {
        $classNames = [];

        foreach (self::autoloadPrefixes($gacelaRoot) as $prefix => $directory) {
            foreach (self::classNamesUnder($prefix, $directory) as $className) {
                $classNames[] = $className;
            }
        }

        sort($classNames);

        return $classNames;
    }

    /**
     * The psr-4 prefixes of Gacela's runtime closure, longest first so a lookup
     * stops at the most specific one.
     *
     * @return array<string, string>
     */
    public static function autoloadPrefixes(string $gacelaRoot): array
    {
        $vendorDir = self::vendorDir($gacelaRoot);

        $prefixes = [
            'Gacela\\Framework\\' => $gacelaRoot . '/src/Framework/',
        ];

        if ($vendorDir === null) {
            return $prefixes;
        }

        $prefixes['Gacela\\Container\\'] = $vendorDir . '/gacela-project/container/src/Container/';
        $prefixes['Psr\\Container\\'] = $vendorDir . '/psr/container/src/';
        // Named for the same reason as the two above: `Psr14EventDispatcher`
        // implements an interface from here, and a class whose interface is
        // missing is dropped from the image with a startup warning rather than
        // linked.
        $prefixes['Psr\\EventDispatcher\\'] = $vendorDir . '/psr/event-dispatcher/src/';

        return $prefixes;
    }

    /**
     * The psr-4 rule: strip the prefix, the rest is the path below its
     * directory. Kept out of the autoloader closure so it can be read on its
     * own -- inside one it is only ever exercised by classes the surrounding
     * application has not already loaded, which in a test suite is none of them.
     *
     * @param array<string, string> $prefixes
     */
    public static function fileFor(string $className, array $prefixes): ?string
    {
        foreach ($prefixes as $prefix => $directory) {
            if (!str_starts_with($className, $prefix)) {
                continue;
            }

            $file = $directory . str_replace('\\', '/', substr($className, strlen($prefix))) . '.php';

            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return list<class-string>
     */
    private static function classNamesUnder(string $prefix, string $directory): array
    {
        $directory = rtrim($directory, '/');

        if (!is_dir($directory)) {
            return [];
        }

        $prefixLength = strlen($directory) + 1;

        $classNames = [];

        /** @var SplFileInfo $file */
        foreach (self::phpFilesIn($directory) as $file) {
            // Windows reports backslashes here, which are also the namespace
            // separator: normalize before either meaning is read into it.
            $path = str_replace('\\', '/', $file->getPathname());

            /** @var class-string $className */
            $className = $prefix . str_replace('/', '\\', substr($path, $prefixLength, -4));

            if (str_starts_with($className, self::EXCLUDED_NAMESPACE)) {
                continue;
            }

            $classNames[] = $className;
        }

        // Not sorted here: the caller sorts the whole closure, and doing it
        // twice would only make the second pass unobservable.
        return $classNames;
    }

    /**
     * Gacela is either the installed package or the repository itself, so the
     * vendor directory is above it in one layout and inside it in the other.
     * Recognised by what it contains rather than by path shape.
     */
    private static function vendorDir(string $gacelaRoot): ?string
    {
        $candidates = [
            dirname($gacelaRoot, 2),
            $gacelaRoot . '/vendor',
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate . '/psr/container/src')) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $prefixes
     */
    private static function registerAutoloader(array $prefixes): void
    {
        spl_autoload_register(static function (string $className) use ($prefixes): void {
            $file = self::fileFor($className, $prefixes);

            if ($file !== null) {
                /** @psalm-suppress UnresolvableInclude the path comes from the prefix map, resolved by fileFor() */
                require_once $file;
            }
        });
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private static function phpFilesIn(string $directory): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
