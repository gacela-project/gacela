<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use Gacela\Framework\Config\PathNormalizerInterface;
use Override;

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

    #[Override]
    public function normalizePathPattern(GacelaConfigItem $configItem): string
    {
        return $this->absolutePathStrategies[self::WITHOUT_SUFFIX]
            ->generateAbsolutePath($configItem->path());
    }

    #[Override]
    public function normalizePathPatternWithEnvironment(GacelaConfigItem $configItem): string
    {
        return $this->absolutePathStrategies[self::WITH_SUFFIX]
            ->generateAbsolutePath($configItem->path());
    }

    #[Override]
    public function normalizePathLocal(GacelaConfigItem $configItem): string
    {
        return $this->absolutePathStrategies[self::WITHOUT_SUFFIX]
            ->generateAbsolutePath($configItem->pathLocal());
    }
}
