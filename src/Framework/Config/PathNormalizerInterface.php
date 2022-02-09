<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;

interface PathNormalizerInterface
{
    public function normalizePathPattern(GacelaConfigItem $configItem): string;

    public function normalizePathPatternWithEnvironment(GacelaConfigItem $configItem): string;

    public function normalizePathLocal(GacelaConfigItem $configItem): string;
}
