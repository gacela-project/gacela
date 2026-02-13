<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\ModuleVersion;

use Gacela\Console\Domain\ModuleVersion\ModuleVersionParserInterface;
use Gacela\Console\Domain\ModuleVersion\TModuleVersion;

use RuntimeException;

use function class_exists;
use function file_exists;
use function is_array;
use function sprintf;

final readonly class YamlModuleVersionParser implements ModuleVersionParserInterface
{
    public function isAvailable(): bool
    {
        return class_exists('Symfony\Component\Yaml\Yaml');
    }

    /**
     * @return array<string, TModuleVersion>
     */
    public function parseVersionsFile(string $filePath): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException(
                'YAML parser not available. Install gacela-project/gacela-yaml-config-reader or symfony/yaml',
            );
        }

        if (!file_exists($filePath)) {
            throw new RuntimeException(sprintf('Version file not found: %s', $filePath));
        }

        /** @var class-string $yamlClass */
        $yamlClass = 'Symfony\Component\Yaml\Yaml';

        /** @var array<string, mixed> $data */
        $data = $yamlClass::parseFile($filePath);

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
