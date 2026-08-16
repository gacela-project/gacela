<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\ClassResolver\StaleBootstrapConfig\Reader;
use PHPUnit\Framework\TestCase;

/**
 * A second bootstrap resolves against its own configuration, not the previous
 * one's.
 *
 * The resolvers behind a `#[ServiceMap]` accessor are held in a trait static --
 * one per using class, which no central reset can reach, and which the static
 * coverage list already records as unclearable. That was safe only while those
 * resolvers held pure memoization. They also held a copy of the merged
 * `gacela.php` from the bootstrap that built them, and
 * `AbstractClassResolver::createInstance()` builds the shared container out of
 * it -- so an application that had used such an accessor once went on resolving
 * every pillar against the previous application's bindings.
 *
 * Silent in every direction: no exception, no event, and the failure lands on
 * whichever class the *new* application binds an interface for, as
 * "no concrete class was found" for a binding that is plainly there.
 */
final class StaleBootstrapConfigTest extends TestCase
{
    private const string ENGLISH_ROOT = __DIR__ . '/StaleBootstrapConfig';

    private const string SPANISH_ROOT = __DIR__ . '/StaleBootstrapConfig/Spanish';

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_the_second_bootstrap_resolves_with_its_own_bindings(): void
    {
        $this->bootstrap(self::ENGLISH_ROOT);
        self::assertSame('hello', (new Reader())->greet());

        $this->bootstrap(self::SPANISH_ROOT);
        self::assertSame('hola', (new Reader())->greet(), 'the resolver served the first bootstrap’s bindings');
    }

    /**
     * The other direction, so the test cannot pass by the two roots happening to
     * agree: whichever ran first, the second one wins.
     */
    public function test_the_order_of_the_two_bootstraps_does_not_decide_the_answer(): void
    {
        $this->bootstrap(self::SPANISH_ROOT);
        self::assertSame('hola', (new Reader())->greet());

        $this->bootstrap(self::ENGLISH_ROOT);
        self::assertSame('hello', (new Reader())->greet());
    }

    private function bootstrap(string $appRootDir): void
    {
        Gacela::bootstrap($appRootDir, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });
    }
}
