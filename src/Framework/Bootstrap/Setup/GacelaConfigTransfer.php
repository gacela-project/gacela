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
     * @param ?list<class-string> $gacelaConfigsToExtend
     * @param ?list<class-string|callable> $plugins
     * @param ?array<string,list<Closure>> $servicesToExtend
     * @param array<string,Closure> $factories
     * @param array<string,Closure> $protectedServices
     * @param array<string,string> $aliases
     * @param array<string,array<class-string,class-string|callable|object>> $contextualBindings
     * @param array<string,Closure> $lazyServices
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
        public readonly ?array $gacelaConfigsToExtend,
        public readonly ?array $plugins,
        public readonly ?array $servicesToExtend,
        public readonly array $factories,
        public readonly array $protectedServices,
        public readonly array $aliases,
        public readonly array $contextualBindings,
        public readonly array $lazyServices,
    ) {
    }
}
