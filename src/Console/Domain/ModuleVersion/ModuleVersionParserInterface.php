<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleVersion;

interface ModuleVersionParserInterface
{
    /**
     * @return array<string, TModuleVersion>
     */
    public function parseVersionsFile(string $filePath): array;

    public function isAvailable(): bool;
}
