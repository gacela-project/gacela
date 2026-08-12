<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

interface PathNormalizerInterface
{
    public function normalizePathPattern(GacelaConfigItem $configItem): string;

    public function normalizePathPatternWithEnvironment(GacelaConfigItem $configItem): string;

    /**
     * One pattern per link of the environment-and-dimensions chain, most
     * general first: `app-prod.php`, then `app-prod-eu.php`, and so on. With
     * no dimension declared this is exactly the environment pattern.
     *
     * @return list<string>
     */
    public function normalizePathPatternsWithSuffixes(GacelaConfigItem $configItem): array;

    public function normalizePathLocal(GacelaConfigItem $configItem): string;
}
