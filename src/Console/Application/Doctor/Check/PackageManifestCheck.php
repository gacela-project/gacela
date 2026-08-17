<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\PackageManifest\ComposerPackageFinder;
use Gacela\Console\Domain\PackageManifest\NamespacePackageMap;
use Gacela\Console\Domain\PackageManifest\PackageManifestChecker;
use Gacela\Console\Domain\PackageManifest\UndeclaredImport;
use Gacela\Framework\Bootstrap\Package\InstalledPackagesReader;

use function count;
use function sprintf;

/**
 * Reports a package importing a namespace its own manifest never declares.
 *
 * Inside a monorepo the root autoloader supplies everything, so a sub-package
 * with an incomplete `require` works perfectly until the day somebody installs
 * it alone.
 */
final class PackageManifestCheck implements HealthCheck
{
    private const string UNDECLARED_DETAIL = '%s imports %s, provided by %s, which its composer.json never mentions';

    public function __construct(
        private readonly string $appRootDir,
        private readonly ComposerPackageFinder $packageFinder = new ComposerPackageFinder(),
        private readonly PackageManifestChecker $checker = new PackageManifestChecker(),
    ) {
    }

    public function name(): string
    {
        return 'package manifests';
    }

    public function run(): CheckResult
    {
        $packages = $this->packageFinder->findIn($this->appRootDir);

        if ($packages === []) {
            return CheckResult::ok($this->name(), 'no named composer package to check');
        }

        $installed = (new InstalledPackagesReader($this->appRootDir))->read();

        // Without installed.json an import cannot be attributed to a package,
        // and guessing would name the wrong manifest to fix.
        if ($installed === null) {
            return CheckResult::ok($this->name(), 'no vendor/composer/installed.json — nothing to map imports against');
        }

        $findings = $this->checker->check($packages, NamespacePackageMap::from($packages, $installed));

        if ($findings === []) {
            return CheckResult::ok(
                $this->name(),
                sprintf('%d package(s) declare everything they import', count($packages)),
            );
        }

        return CheckResult::warn(
            $this->name(),
            array_map(
                static fn (UndeclaredImport $finding): string => sprintf(
                    self::UNDECLARED_DETAIL,
                    $finding->package,
                    $finding->import,
                    $finding->providedBy,
                ),
                $findings,
            ),
            'add it to `require`, or to `suggest` when the dependency is optional by design',
        );
    }

}
