<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Exception\GacelaNotBootstrappedException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

/**
 * Every way of reaching Gacela before it is bootstrapped answers the same.
 *
 * Stated once, as a set, rather than left to each entry point's own test.
 * `Config::getInstance()` had drifted to a bare `RuntimeException` naming an
 * `@internal` method, and no test noticed, because each site was asserted alone
 * and the assertion was the base class the drifted exception still matched.
 *
 * The type is what the guard is for. `ConstructorInspector` and
 * `DependencyTreeInspector` both catch `GacelaNotBootstrappedException` to
 * degrade instead of failing, so an entry point that throws anything else is a
 * hole in a handler written for exactly this condition -- and it is silent, in
 * the way an exception nobody catches usually is not.
 */
final class NotBootstrappedContractTest extends TestCase
{
    protected function tearDown(): void
    {
        // Leave the process bootstrapped, or every later test in it inherits
        // the state this one creates on purpose.
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function entryPointProvider(): iterable
    {
        // The one a project reaches first: every pillar goes through it, so a
        // `new SomeFacade()` before the bootstrap lands here.
        yield 'Config::getInstance()' => [Config::getInstance(...)];

        yield 'Gacela::container()' => [Gacela::container(...)];

        yield 'Gacela::rootDir()' => [Gacela::rootDir(...)];
    }

    /**
     * @param callable(): mixed $entryPoint
     */
    #[DataProvider('entryPointProvider')]
    public function test_it_throws_the_not_bootstrapped_exception(callable $entryPoint): void
    {
        $this->unbootstrap();

        $this->expectException(GacelaNotBootstrappedException::class);
        $this->expectExceptionMessage(GacelaNotBootstrappedException::MESSAGE);

        $entryPoint();
    }

    /**
     * Guards the case above against passing for the wrong reason: if
     * `unbootstrap()` stopped clearing something, the entry point would answer
     * normally and the test would fail rather than pass -- but if an entry
     * point were dropped from the provider, nothing would say so. This asserts
     * every one of them is still reached.
     */
    public function test_every_entry_point_is_exercised(): void
    {
        $names = array_keys([...self::entryPointProvider()]);

        self::assertSame(
            ['Config::getInstance()', 'Gacela::container()', 'Gacela::rootDir()'],
            $names,
        );
    }

    /**
     * Sanity: the reset really does un-bootstrap, so a green run above is the
     * exception being thrown and not the entry point quietly succeeding.
     */
    public function test_the_reset_actually_unbootstraps(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
        self::assertSame(__DIR__, Gacela::rootDir());

        $this->unbootstrap();

        $threw = false;
        try {
            Gacela::rootDir();
        } catch (Throwable) {
            $threw = true;
        }

        self::assertTrue($threw, 'unbootstrap() left Gacela usable, so the contract tests prove nothing');
    }

    /**
     * Both statics behind the three entry points, put back to the state a
     * process has before `Gacela::bootstrap()` runs.
     */
    private function unbootstrap(): void
    {
        Config::resetInstance();

        $gacela = new ReflectionClass(Gacela::class);
        $gacela->getProperty('appRootDir')->setValue($gacela, value: null);
        $gacela->getProperty('mainContainer')->setValue($gacela, value: null);
    }
}
