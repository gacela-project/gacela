<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_values;
use function get_declared_classes;
use function str_starts_with;

/**
 * The guarantee `docs/production-performance.md` rests on:
 *
 * > Bootstrapping does not walk your modules -- it reads the configuration and
 * > builds the container, and costs the same in a project with five modules as
 * > in one with five hundred.
 * >
 * > Resolution is charged per module you actually **touch**, not per module you
 * > have.
 *
 * It is the reason the page tells you to tune bootstrap and class loading
 * rather than the size of the codebase, so it is worth more than a sentence.
 *
 * Asserted structurally rather than by timing: a stopwatch on a sub-100µs
 * bootstrap is noise, while "which classes did this load" is exact and cannot
 * go flaky. Measured at the time of writing, for the record: bootstrap was
 * 45.08 / 44.83 / 44.90 µs with 10 / 100 / 500 modules on disk.
 *
 * Every class name here is a string literal on purpose. Writing `AlphaFacade::class`
 * would autoload it and make the test pass or fail on its own reference.
 */
final class BootstrapDoesNotWalkModulesTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/ModuleCountFixture';

    private const string FIXTURE_NAMESPACE = 'GacelaTest\Integration\Framework\Bootstrap\ModuleCountFixture\\';

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    /**
     * Asserted about Alpha and Gamma, the two modules no test here touches:
     * `get_declared_classes()` is process-global and this suite runs in random
     * order, so asking "nothing at all is loaded" would fail whenever the test
     * below happened to run first and load Beta.
     */
    public function test_bootstrapping_loads_none_of_the_projects_modules(): void
    {
        $this->bootstrapFixture();

        $loaded = $this->loadedFixtureClasses();

        self::assertNotContains(self::FIXTURE_NAMESPACE . 'Alpha\AlphaFacade', $loaded);
        self::assertNotContains(self::FIXTURE_NAMESPACE . 'Alpha\AlphaFactory', $loaded);
        self::assertNotContains(self::FIXTURE_NAMESPACE . 'Gamma\GammaFacade', $loaded);
        self::assertNotContains(self::FIXTURE_NAMESPACE . 'Gamma\GammaFactory', $loaded);
    }

    /**
     * The other half: touching one module loads that module and leaves the
     * others alone, which is what "charged per module you touch" means.
     *
     * It also proves the check above is not vacuous -- the same helper does
     * report a module once something reaches for it. Beta belongs to this test;
     * the one above asks about the two nothing here touches.
     */
    public function test_touching_one_module_loads_only_that_one(): void
    {
        $this->bootstrapFixture();

        $facadeClass = self::FIXTURE_NAMESPACE . 'Beta\BetaFacade';
        /** @var callable():string $reachTheFactory */
        $reachTheFactory = [new $facadeClass(), 'name'];

        // Calling it is the point: the Factory resolves on first use, so this
        // is what makes BetaFactory load.
        self::assertSame('Beta', $reachTheFactory());

        $loaded = $this->loadedFixtureClasses();

        self::assertContains(self::FIXTURE_NAMESPACE . 'Beta\BetaFacade', $loaded);
        self::assertContains(self::FIXTURE_NAMESPACE . 'Beta\BetaFactory', $loaded);
        self::assertNotContains(self::FIXTURE_NAMESPACE . 'Alpha\AlphaFacade', $loaded);
        self::assertNotContains(self::FIXTURE_NAMESPACE . 'Gamma\GammaFacade', $loaded);
    }

    private function bootstrapFixture(): void
    {
        Gacela::bootstrap(self::FIXTURE_DIR, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });
    }

    /**
     * @return list<string>
     */
    private function loadedFixtureClasses(): array
    {
        return array_values(array_filter(
            get_declared_classes(),
            static fn (string $class): bool => str_starts_with($class, self::FIXTURE_NAMESPACE),
        ));
    }
}
