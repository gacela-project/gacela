<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\ModuleVersion;

use Gacela\Console\Domain\ModuleVersion\ModuleVersionParserInterface;
use Gacela\Console\Domain\ModuleVersion\TModuleVersion;

use RuntimeException;

use function file_exists;
use function is_array;
use function sprintf;

/**
 * Fallback parser that reads PHP arrays from .php files
 */
final readonly class ArrayModuleVersionParser implements ModuleVersionParserInterface
{
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @return array<string, TModuleVersion>
     */
    public function parseVersionsFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException(sprintf('Version file not found: %s', $filePath));
        }

        /** @var mixed $data */
        $data = include $filePath;

        if (!is_array($data)) {
            throw new RuntimeException('Version file must return an array');
        }

        return $this->parseData($data);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, TModuleVersion>
     */
    private function parseData(array $data): array
    {
        $modules = [];

        foreach ($data as $moduleName => $moduleData) {
            if (!is_array($moduleData)) {
                continue;
            }

            $version = (string)($moduleData['version'] ?? '0.0.0');
            $requires = [];

            if (isset($moduleData['requires']) && is_array($moduleData['requires'])) {
                foreach ($moduleData['requires'] as $reqName => $reqVersion) {
                    $requires[(string)$reqName] = (string)$reqVersion;
                }
            }

            $modules[(string)$moduleName] = new TModuleVersion(
                moduleName: (string)$moduleName,
                version: $version,
                requiredModules: $requires,
            );
        }

        return $modules;
    }
}
