<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendProviderServiceMerge;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ExtendProviderService\Catalog\CatalogFacade;
use GacelaTest\Feature\Framework\ExtendProviderService\Catalog\CatalogProvider;
use PHPUnit\Framework\TestCase;

/**
 * Two config sources decorating one Provider binding. Neither silences the
 * other: an extension is a contribution, so a project adding one must not
 * drop the one a package shipped.
 */
final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_extensions_from_both_sources_apply(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->extendProviderService(
                CatalogProvider::class,
                CatalogProvider::LABEL,
                static fn (array $labels): array => [...$labels, 'closure-one'],
            );
            $config->extendProviderService(
                CatalogProvider::class,
                CatalogProvider::LABEL,
                static fn (array $labels): array => [...$labels, 'closure-two'],
            );
        });

        // The bootstrap closure's setup is the base and gacela.php merges onto
        // it, so the closure's extension runs first -- the same order the
        // plugin stacks follow.
        self::assertSame('catalog-closure-one-closure-two-file-one-file-two', (new CatalogFacade())->label());
    }
}
