<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\PathNormalizer;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use Gacela\Framework\Config\PathNormalizerInterface;

final class AbsolutePathNormalizer implements PathNormalizerInterface
{
    public const PATTERN = 'PATTERN';
    public const PATTERN_WITH_ENV = 'PATTERN_WITH_ENV';
    public const LOCAL = 'LOCAL';

    /** @var array<string,AbsolutePathStrategyInterface> */
    private array $absolutePathStrategies;

    /**
     * @param array<string,AbsolutePathStrategyInterface> $absolutePathStrategies
     */
    public function __construct(array $absolutePathStrategies)
    {
        $this->absolutePathStrategies = $absolutePathStrategies;
    }

    public function normalizePathPattern(GacelaConfigItem $configItem): string
    {
        return $this->absolutePathStrategies[self::PATTERN]
            ->generateAbsolutePath($configItem->path());
    }

    public function normalizePathPatternWithEnv(GacelaConfigItem $configItem): string
    {
        return $this->absolutePathStrategies[self::PATTERN_WITH_ENV]
            ->generateAbsolutePath($configItem->path());
    }

    public function normalizePathLocal(GacelaConfigItem $configItem): string
    {
        return $this->absolutePathStrategies[self::LOCAL]
            ->generateAbsolutePath($configItem->pathLocal());
    }
}
