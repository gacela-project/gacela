<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

/**
 * @psalm-type BindingsMap = array<class-string, class-string|callable|object>
 *
 * @psalm-import-type SuffixTypes from SuffixTypesBuilder
 */
interface GacelaConfigFileInterface
{
    /**
     * @return list<GacelaConfigItem>
     */
    public function getConfigItems(): array;

    /**
     * Map interfaces to concrete classes or callable (which will be resolved on runtime).
     * This is util to inject dependencies to Gacela services (such as Factories, for example) via their constructor.
     *
     * @return BindingsMap
     */
    public function getBindings(): array;

    /**
     * @return SuffixTypes
     */
    public function getSuffixTypes(): array;

    /**
     * Merge one GacelaConfigFile with another.
     */
    public function merge(self $other): self;
}
