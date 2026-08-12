<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\PackageManifest;

use FilesystemIterator;
use Gacela\Console\Domain\ModuleGraph\PhpImportParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function in_array;
use function strlen;

/**
 * Finds imports a package's own manifest does not account for.
 *
 * A module directory that ships as its own package can import a namespace its
 * `composer.json` never mentions. Inside the monorepo the root autoloader
 * supplies it and nothing fails; installed on its own, it fatals. That is a bug
 * this repository shipped once already, and no tool here would have caught it.
 *
 * Only this direction is checked. The reverse -- a requirement nothing imports
 * -- is frequently correct: a package requires a concrete implementation so
 * that the container can resolve it at runtime, and never names the class.
 * Telling those two apart needs a reviewed exception per entry, which is a
 * second declaration surface for a weaker finding.
 */
final class PackageManifestChecker
{
    public function __construct(
        private readonly PhpImportParser $importParser = new PhpImportParser(),
    ) {
    }

    /**
     * @param list<ComposerPackage> $packages
     *
     * @return list<UndeclaredImport>
     */
    public function check(array $packages, NamespacePackageMap $packageMap): array
    {
        $findings = [];

        foreach ($packages as $package) {
            foreach ($this->undeclaredImportsOf($package, $packageMap, $packages) as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * @param list<ComposerPackage> $allPackages
     *
     * @return list<UndeclaredImport>
     */
    private function undeclaredImportsOf(ComposerPackage $package, NamespacePackageMap $packageMap, array $allPackages): array
    {
        // Keyed by the missing package, so the first import that needs it is
        // the one reported: a reader fixes it once, and fifty lines naming one
        // requirement bury the other packages that are also missing.
        $findings = [];

        foreach ($this->autoloadedFilesOf($package, $allPackages) as $file) {
            $source = file_get_contents($file);

            if ($source === false) {
                continue;
            }

            foreach ($this->importParser->importsIn($source) as $import) {
                $candidates = $packageMap->packagesProviding($import);
                // Nothing claims the namespace: a global class, or one this
                // repository does not manage. Either way there is no package to
                // name in a requirement.
                if ($candidates === []) {
                    continue;
                }

                if (in_array($package->name, $candidates, true)) {
                    continue;
                }

                // Declaring any one claimant is enough. Where several packages
                // publish one prefix, the manifest naming any of them is what
                // makes the class arrive.
                foreach ($candidates as $candidate) {
                    if ($package->declares($candidate)) {
                        continue 2;
                    }
                }

                $providedBy = implode(' or ', $candidates);

                $findings[$providedBy] ??= new UndeclaredImport($package->name, $file, $import, $providedBy);
            }
        }

        return array_values($findings);
    }

    /**
     * The php files a consumer actually receives: the ones under the autoload
     * prefixes this package owns.
     *
     * A monorepo root commonly autoloads its sub-packages as well, so that one
     * `composer install` serves development. Where two manifests declare the
     * same prefix, the deeper one owns it -- otherwise the root is held to the
     * imports of every package beneath it, and the requirement it asks for
     * belongs in a different manifest.
     *
     * @param list<ComposerPackage> $allPackages
     *
     * @return list<string>
     */
    private function autoloadedFilesOf(ComposerPackage $package, array $allPackages): array
    {
        $files = [];

        foreach ($package->autoloadPrefixes as $prefix => $directory) {
            if (!$this->ownsPrefix($package, $prefix, $allPackages)) {
                continue;
            }

            $absolute = $package->rootDir . DIRECTORY_SEPARATOR . trim($directory, '/\\');

            if (!is_dir($absolute)) {
                continue;
            }

            foreach ($this->phpFilesIn($absolute) as $file) {
                // Keyed by path so two prefixes pointing at one directory yield
                // the file once, and holding the path as the value too keeps it
                // the thing being collected rather than a membership flag.
                $files[$file] = $file;
            }
        }

        $paths = array_values($files);
        sort($paths);

        return $paths;
    }

    /**
     * @param list<ComposerPackage> $allPackages
     */
    private function ownsPrefix(ComposerPackage $package, string $prefix, array $allPackages): bool
    {
        foreach ($allPackages as $other) {
            if ($other->name === $package->name) {
                continue;
            }

            if (!isset($other->autoloadPrefixes[$prefix])) {
                continue;
            }

            // Deeper root wins: the sub-package is the one whose manifest a
            // reader has to fix, and it is the one published on its own.
            if (strlen($other->rootDir) > strlen($package->rootDir)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
