<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

use function is_string;

/**
 * One `extra.gacela.config` declaration, resolved to a file on disk.
 *
 * Says nothing about whether that file exists or is usable. Discovery answers
 * that when it reads it, and `doctor` answers it without booting -- both from
 * this, so both are talking about the same declaration.
 */
final class PackageConfigDeclaration
{
    /**
     * @param string $name         the Composer package name, as its manifest writes it
     * @param string $declaredPath the path exactly as `extra.gacela.config` gives it,
     *                             kept so a message can quote what the package author wrote
     *                             rather than the absolute path it resolved to
     * @param string $configFile   that path resolved against the package's install directory
     */
    public function __construct(
        public readonly string $name,
        public readonly string $declaredPath,
        public readonly string $configFile,
    ) {
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public static function fromArray(array $row): ?self
    {
        $name = $row['name'] ?? null;
        $declaredPath = $row['declaredPath'] ?? null;
        $configFile = $row['configFile'] ?? null;

        if (!is_string($name) || !is_string($declaredPath) || !is_string($configFile)) {
            return null;
        }

        return new self($name, $declaredPath, $configFile);
    }

    /**
     * @return array{name: string, declaredPath: string, configFile: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'declaredPath' => $this->declaredPath,
            'configFile' => $this->configFile,
        ];
    }
}
