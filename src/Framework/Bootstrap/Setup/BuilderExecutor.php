<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\BuilderConfigurationInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;

/**
 * Handles execution of builder methods for SetupGacela.
 *
 * @psalm-import-type ExternalServicesMap from BuilderConfigurationInterface
 */
final class BuilderExecutor
{
    public function __construct(
        private readonly Properties $properties,
    ) {
    }

    public function buildAppConfig(AppConfigBuilder $parentBuilder): AppConfigBuilder
    {
        $builder = $this->properties->appConfigBuilder ?? $parentBuilder;
        ($this->properties->appConfigFn)($builder);

        return $builder;
    }

    /**
     * @param ExternalServicesMap $externalServices
     */
    public function buildBindings(
        BindingsBuilder $parentBuilder,
        array $externalServices,
    ): BindingsBuilder {
        $builder = $this->properties->bindingsBuilder ?? $parentBuilder;

        ($this->properties->bindingsFn)(
            $builder,
            array_merge($this->properties->externalServices ?? [], $externalServices)
        );

        return $builder;
    }

    public function buildSuffixTypes(SuffixTypesBuilder $parentBuilder): SuffixTypesBuilder
    {
        $builder = $this->properties->suffixTypesBuilder ?? $parentBuilder;
        ($this->properties->suffixTypesFn)($builder);

        return $builder;
    }
}
