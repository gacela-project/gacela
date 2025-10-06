<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use Gacela\Framework\Config\PathNormalizerInterface;

final class AbsolutePathNormalizer implements PathNormalizerInterface
{
    public const WITHOUT_SUFFIX = 'WITHOUT_SUFFIX';

    public const WITH_SUFFIX = 'WITH_SUFFIX';

    /**
     * @param array<string,AbsolutePathStrategyInterface> $absolutePathStrategies
     */
    public function __construct(
        private array $absolutePathStrategies,
    ) {
    }

    public function normalizePathPattern(GacelaConfigItem $configItem): string
    {
        return $this->absolutePathStrategies[self::WITHOUT_SUFFIX]
            ->generateAbsolutePath($configItem->path());
    }

    public function normalizePathPatternWithEnvironment(GacelaConfigItem $configItem): string
    {
        return $this->absolutePathStrategies[self::WITH_SUFFIX]
            ->generateAbsolutePath($configItem->path());
    }

    public function normalizePathLocal(GacelaConfigItem $configItem): string
    {
        return $this->absolutePathStrategies[self::WITHOUT_SUFFIX]
            ->generateAbsolutePath($configItem->pathLocal());
    }
}
