<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Closure;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

final class GacelaConfigTransfer
{
    /**
     * @param ?array<string, class-string|object|callable> $externalServices
     * @param ?list<string> $projectNamespaces
     * @param ?array<string,mixed> $configKeyValues
     * @param ?list<callable> $genericListeners
     * @param ?array<class-string,list<callable>> $specificListeners
     * @param ?list<class-string> $gacelaConfigsToExtend
     * @param ?list<class-string|callable> $plugins
     * @param ?array<string,list<Closure>> $servicesToExtend
     */
    public function __construct(
        public readonly AppConfigBuilder $appConfigBuilder,
        public readonly SuffixTypesBuilder $suffixTypesBuilder,
        public readonly BindingsBuilder $bindingsBuilder,
        public readonly ?array $externalServices,
        public readonly ?bool $shouldResetInMemoryCache,
        public readonly ?bool $fileCacheEnabled,
        public readonly ?string $fileCacheDirectory,
        public readonly ?array $projectNamespaces,
        public readonly ?array $configKeyValues,
        public readonly ?array $genericListeners,
        public readonly ?array $specificListeners,
        public readonly ?bool $areEventListenersEnabled,
        public readonly ?array $gacelaConfigsToExtend,
        public readonly ?array $plugins,
        public readonly ?array $servicesToExtend,
    ) {
    }
}
