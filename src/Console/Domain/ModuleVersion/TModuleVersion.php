<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleVersion;

final class TModuleVersion
{
    /**
     * @param non-empty-string $moduleName
     * @param non-empty-string $version
     * @param array<string, string> $requiredModules
     */
    public function __construct(
        public readonly string $moduleName,
        public readonly string $version,
        public readonly array $requiredModules = [],
    ) {
    }
}
