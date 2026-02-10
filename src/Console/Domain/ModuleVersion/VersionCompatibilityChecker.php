<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleVersion;

use function sprintf;
use function version_compare;

final readonly class VersionCompatibilityChecker
{
    /**
     * @param array<string, TModuleVersion> $moduleVersions
     */
    public function __construct(
        private array $moduleVersions,
    ) {
    }

    public function checkCompatibility(): TVersionCompatibilityResult
    {
        $errors = [];
        $warnings = [];

        foreach ($this->moduleVersions as $moduleName => $moduleVersion) {
            foreach ($moduleVersion->requiredModules as $requiredName => $requiredVersion) {
                if (!isset($this->moduleVersions[$requiredName])) {
                    $errors[] = sprintf(
                        'Module "%s" requires "%s" but it is not defined in the version matrix',
                        $moduleName,
                        $requiredName
                    );
                    continue;
                }

                $actualVersion = $this->moduleVersions[$requiredName]->version;

                if (!$this->isVersionCompatible($actualVersion, $requiredVersion)) {
                    $errors[] = sprintf(
                        'Module "%s" requires "%s" version %s but found %s',
                        $moduleName,
                        $requiredName,
                        $requiredVersion,
                        $actualVersion
                    );
                }
            }
        }

        return new TVersionCompatibilityResult(
            isCompatible: $errors === [],
            errors: $errors,
            warnings: $warnings,
        );
    }

    private function isVersionCompatible(string $actualVersion, string $requiredVersion): bool
    {
        // Support for caret (^) constraint like ^1.0
        if (str_starts_with($requiredVersion, '^')) {
            $baseVersion = substr($requiredVersion, 1);

            // Parse major version
            $parts = explode('.', $baseVersion);
            $majorVersion = (int)($parts[0] ?? 0);

            // For ^1.0, accept >= 1.0 and < 2.0
            return version_compare($actualVersion, $baseVersion, '>=')
                && version_compare($actualVersion, (string)($majorVersion + 1) . '.0', '<');
        }

        // Support for tilde (~) constraint like ~1.2
        if (str_starts_with($requiredVersion, '~')) {
            $baseVersion = substr($requiredVersion, 1);
            $parts = explode('.', $baseVersion);

            // For ~1.2, accept >= 1.2 and < 1.3
            if (count($parts) >= 2) {
                $nextMinor = (string)((int)$parts[0]) . '.' . (string)((int)$parts[1] + 1);

                return version_compare($actualVersion, $baseVersion, '>=')
                    && version_compare($actualVersion, $nextMinor, '<');
            }
        }

        // Support for >= operator
        if (str_starts_with($requiredVersion, '>=')) {
            return version_compare($actualVersion, substr($requiredVersion, 2), '>=');
        }

        // Support for > operator
        if (str_starts_with($requiredVersion, '>')) {
            return version_compare($actualVersion, substr($requiredVersion, 1), '>');
        }

        // Support for <= operator
        if (str_starts_with($requiredVersion, '<=')) {
            return version_compare($actualVersion, substr($requiredVersion, 2), '<=');
        }

        // Support for < operator
        if (str_starts_with($requiredVersion, '<')) {
            return version_compare($actualVersion, substr($requiredVersion, 1), '<');
        }

        // Exact version match
        return version_compare($actualVersion, $requiredVersion, '=');
    }
}
