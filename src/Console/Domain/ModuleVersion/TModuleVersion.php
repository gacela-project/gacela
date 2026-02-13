<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleVersion;

final readonly class TModuleVersion
{
    /**
     * @param non-empty-string $moduleName
     * @param non-empty-string $version
     * @param array<string, string> $requiredModules
     */
    public function __construct(
        public string $moduleName,
        public string $version,
        public array $requiredModules = [],
    ) {
    }
}
