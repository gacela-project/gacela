<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FileCache;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleA\Facade as ThirdPartyModuleAFacade;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

/**
 * Two bootstraps of one app root sharing one cache dir with different
 * `projectNamespaces` -- the standard multi-entrypoint layout (#681). The
 * on-disk class-name cache must answer each bootstrap with its own
 * resolution, not whichever bootstrap wrote the file first.
 */
final class FileCacheBootstrapIdentityFeatureTest extends TestCase
{
    private const CACHE_DIR = __DIR__ . '/bootstrap-identity-cache';

    public static function tearDownAfterClass(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });

        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    protected function setUp(): void
    {
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    public function test_a_second_bootstrap_resolves_with_its_own_configuration(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache(self::CACHE_DIR);
            $config->setProjectNamespaces([
                'GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\src\Main',
            ]);
        });

        self::assertSame(
            'Overridden, from src\CompanyA\ModuleA::StringA',
            (new ThirdPartyModuleAFacade())->stringValueA1(),
            'the first bootstrap wants its project-namespace override',
        );

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->enableFileCache(self::CACHE_DIR);
            // No setProjectNamespaces(): this bootstrap wants the vendor wiring.
        });

        self::assertSame(
            'Hi, from vendor\ThirdParty\ModuleA::StringA1',
            (new ThirdPartyModuleAFacade())->stringValueA1(),
            "the second bootstrap must not inherit the first one's override through the shared cache file",
        );
    }
}
