<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendProviderServiceMerge;

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\ExtendProviderService\Catalog\CatalogProvider;

return static function (GacelaConfig $config): void {
    // A second config source decorating the same Provider binding the
    // bootstrap closure decorates.
    $config->extendProviderService(
        CatalogProvider::class,
        CatalogProvider::LABEL,
        static fn (array $labels): array => [...$labels, 'file-one'],
    );
    $config->extendProviderService(
        CatalogProvider::class,
        CatalogProvider::LABEL,
        static fn (array $labels): array => [...$labels, 'file-two'],
    );
};
