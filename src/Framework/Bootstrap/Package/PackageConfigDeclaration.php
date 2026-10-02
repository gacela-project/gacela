<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

use function is_array;
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
     * @param array<string, list<string>> $sources the package's `autoload.psr-4` map, each
     *                                             directory resolved like `$configFile`:
     *                                             where its `#[Plugin]`, `#[Tag]` and
     *                                             `#[AsListener]` classes are read
     */
    public function __construct(
        public readonly string $name,
        public readonly string $declaredPath,
        public readonly string $configFile,
        public readonly array $sources = [],
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

        $sources = $row['sources'] ?? null;

        // A row cached before the sources were recorded has none, and reading
        // it as "no sources" would hide the package's attribute members until
        // the next `composer install`.
        if (!is_string($name) || !is_string($declaredPath) || !is_string($configFile) || !is_array($sources)) {
            return null;
        }

        $valid = [];
        foreach ($sources as $namespace => $directories) {
            if (!is_string($namespace) || !is_array($directories)) {
                return null;
            }

            $valid[$namespace] = [];
            /** @var mixed $directory */
            foreach ($directories as $directory) {
                if (is_string($directory)) {
                    $valid[$namespace][] = $directory;
                }
            }
        }

        return new self($name, $declaredPath, $configFile, $valid);
    }

    /**
     * @return array{name: string, declaredPath: string, configFile: string, sources: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'declaredPath' => $this->declaredPath,
            'configFile' => $this->configFile,
            'sources' => $this->sources,
        ];
    }
}
