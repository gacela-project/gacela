<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\SetupGacela;

/**
 * Initializes a SetupGacela instance from a GacelaConfigTransfer DTO.
 * This class encapsulates the logic for populating all properties from the transfer object.
 */
final class SetupInitializer
{
    public function __construct(
        private readonly SetupGacela $setup,
    ) {
    }

    public function initializeFromTransfer(GacelaConfigTransfer $dto): SetupGacela
    {
        return $this->setup
            ->setExternalServices($dto->externalServices)
            ->setAppConfigBuilder($dto->appConfigBuilder)
            ->setSuffixTypesBuilder($dto->suffixTypesBuilder)
            ->setBindingsBuilder($dto->bindingsBuilder)
            ->setShouldResetInMemoryCache($dto->shouldResetInMemoryCache)
            ->setFileCacheEnabled($dto->fileCacheEnabled)
            ->setFileCacheDirectory($dto->fileCacheDirectory)
            ->setProjectNamespaces($dto->projectNamespaces)
            ->setConfigKeyValues($dto->configKeyValues)
            ->setAreEventListenersEnabled($dto->areEventListenersEnabled)
            ->setGenericListeners($dto->genericListeners)
            ->setSpecificListeners($dto->specificListeners)
            ->setGacelaConfigsToExtend($dto->gacelaConfigsToExtend)
            ->setPlugins($dto->plugins)
            ->setServicesToExtend($dto->servicesToExtend)
            ->setFactories($dto->factories)
            ->setProtectedServices($dto->protectedServices)
            ->setAliases($dto->aliases)
            ->setContextualBindings($dto->contextualBindings);
    }
}
