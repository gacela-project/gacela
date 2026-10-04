<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ResetFromGacelaFile;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\Bootstrap\ResetFromGacelaFile\Vendor\Pay\PayFacade;
use PHPUnit\Framework\TestCase;

/**
 * `resetInMemoryCache()` written in `gacela.php` holds when the bootstrap
 * also gets a closure, though that file is merged only after the closure.
 */
final class ResetFromGacelaFileTest extends TestCase
{
    public function test_a_second_bootstrap_resolves_with_its_own_project_namespaces(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });
        self::assertSame('vendor', (new PayFacade())->who());

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
            $config->setProjectNamespaces([__NAMESPACE__ . '\\App']);
        });
        self::assertSame('app override', (new PayFacade())->who());
    }
}
