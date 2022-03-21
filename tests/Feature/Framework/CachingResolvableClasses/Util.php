<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CachingResolvableClasses;

use Gacela\Framework\Gacela;

final class Util
{
    public static function gacelaBootstrapWithCache(bool $cachedEnabled): void
    {
        Gacela::bootstrap(__DIR__, [
            'resolvable-class-names-cache-enabled' => $cachedEnabled,
        ]);
    }

    public static function loadGacelaCacheFiles(): void
    {
        (new ModuleA\Facade())->loadGacelaCacheFile();
        (new ModuleB\Facade())->loadGacelaCacheFile();
        (new ModuleC\Facade())->loadGacelaCacheFile();
        (new ModuleD\Facade())->loadGacelaCacheFile();
        (new ModuleE\Facade())->loadGacelaCacheFile();
        (new ModuleF\Facade())->loadGacelaCacheFile();
        (new ModuleG\Facade())->loadGacelaCacheFile();
        (new ModuleH\Facade())->loadGacelaCacheFile();
        (new ModuleI\Facade())->loadGacelaCacheFile();
        (new ModuleJ\Facade())->loadGacelaCacheFile();
    }
}
