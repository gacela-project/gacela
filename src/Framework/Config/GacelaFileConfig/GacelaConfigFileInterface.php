<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig;

use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

/**
 * @psalm-type BindingsMap = array<string, class-string|callable|object>
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
     * Paths besides the config files whose change rebuilds the merged config
     * cache, relative to the application root.
     *
     * @return list<string>
     */
    public function getConfigCacheWatchPaths(): array;

    /**
     * Whether `cache:warm` writes a merged config cache that checks its sources
     * on every hit, like one written on a miss, instead of a trusted one.
     */
    public function isWarmedConfigCacheVerified(): bool;

    /**
     * Merge one GacelaConfigFile with another.
     */
    public function merge(self $other): self;
}
