<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\SelfReferentialProvides;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Exception\CircularProvidesException;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\SelfReferentialProvides\Broken\Facade;
use GacelaTest\Feature\Framework\SelfReferentialProvides\Broken\Provider;
use PHPUnit\Framework\TestCase;

/**
 * #870, on the path the report came in on: `getProvidedDependency()` through a
 * real bootstrap, rather than a container built by hand.
 *
 * The declaration is accepted everywhere it could be refused -- `composer
 * install` passes, `doctor` passes, the module resolves -- and the first
 * request for that one id is where it fails.
 */
final class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    public function test_the_rest_of_the_module_resolves(): void
    {
        self::assertSame('sound', (new Facade())->sound());
    }

    public function test_asking_for_the_self_referential_id_names_the_declaration(): void
    {
        $this->expectException(CircularProvidesException::class);
        $this->expectExceptionMessage(Provider::class . '::selfReferential() is declared #[Provides('
            . Provider::SELF_REFERENTIAL_ID . '::class)]');

        (new Facade())->selfReferential();
    }
}
