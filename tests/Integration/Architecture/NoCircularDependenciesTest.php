<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_values;
use function implode;
use function sprintf;

/**
 * Guards the source tree against circular dependencies, at both the class and
 * the namespace (package) level.
 *
 * The graph is built from the AST — see {@see DependencyGraph} for why an
 * import-based graph is not good enough — and Tarjan's algorithm reports every
 * strongly connected component larger than one node.
 *
 * Cycles that are cohesive by design are allow-listed below with a rationale.
 * Any *new* cycle fails the test.
 *
 * Note the allow-list forgives a whole component: while a component is listed,
 * new edges *among its existing members* are not detected. That is the cost of
 * listing the large bootstrap component, and a reason to keep shrinking it.
 */
final class NoCircularDependenciesTest extends TestCase
{
    private const SRC = __DIR__ . '/../../../src';

    /**
     * Each allow-listed cycle is the sorted list of its member classes joined by ' | '.
     *
     * @var list<string>
     */
    private const ALLOWED_CLASS_CYCLES = [
        // The bootstrap knot. Config needs a ConfigFactory to read `gacela.php`, that
        // file produces a SetupGacela, setup resolves extenders and health checks
        // through the Container, and the Container reads its bindings back off Config:
        //
        //   Config -> ConfigFactory -> GacelaConfigUsingGacelaPhpFileFactory
        //     -> SetupGacela -> {GacelaConfigExtender, HealthCheckRegistry}
        //     -> Container -> Config
        //
        // `Container::withConfig(Config)` is the single edge every path returns
        // through, and inverting it (Config builds the Container, not the reverse)
        // is what unpicks this component. That is a BC break on a public method, so
        // it is deferred; until then the whole component is listed.
        //
        // A second, smaller edge for the same 2.0 pass: `SetupGacelaInterface`
        // declares `merge(SetupGacela $other)`, naming its own implementation in
        // its own contract, which no other implementation could satisfy. Dropping
        // it from the interface needs `ConfigFactory` and
        // `GacelaConfigUsingGacelaPhpFileFactory` to type-hint the concrete
        // `SetupGacela`; both are public non-@internal constructors, so it cannot
        // be done in a minor. Deprecating it early was tried and reverted: nothing
        // in Gacela itself could satisfy the deprecation, so it only produced a
        // warning no caller could act on.
        //
        // Two sub-clusters inside it are cohesive on their own merits and are
        // expected to survive even after the knot is cut:
        //   - SetupGacela + its Setup\* strategy helpers: an aggregate and the
        //     strategies extracted from it; the back-edges are parameter/return
        //     types, and SetupMerger alone reads 17 of its constants.
        //   - Container + Locator: a container and its service locator, both
        //     @internal, one concept split across two classes.
        \Gacela\Framework\Bootstrap\AbstractSetupGacela::class
            . ' | Gacela\Framework\Bootstrap\GacelaConfig'
            . ' | Gacela\Framework\Bootstrap\SetupEventDispatcher'
            . ' | Gacela\Framework\Bootstrap\SetupGacela'
            . ' | Gacela\Framework\Bootstrap\SetupGacelaInterface'
            . ' | Gacela\Framework\Bootstrap\Setup\GacelaConfigExtender'
            . ' | Gacela\Framework\Bootstrap\Setup\PropertyMerger'
            . ' | Gacela\Framework\Bootstrap\Setup\SetupInitializer'
            . ' | Gacela\Framework\Bootstrap\Setup\SetupMerger'
            . ' | Gacela\Framework\ClassResolver\Cache\GacelaFileCache'
            . ' | Gacela\Framework\Config\Config'
            . ' | Gacela\Framework\Config\ConfigFactory'
            . ' | Gacela\Framework\Config\ConfigInterface'
            . ' | Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigUsingGacelaPhpFileFactory'
            . ' | Gacela\Framework\Container\Container'
            . ' | Gacela\Framework\Container\Locator'
            . ' | Gacela\Framework\Health\HealthCheckRegistry',
    ];

    /**
     * Package-level cycles. These are looser than class cycles by nature: a
     * namespace pair is cyclic as soon as any one class on each side points at
     * the other, so a framework that configures itself is cyclic here almost by
     * construction — resolving a config class needs the class resolver, and the
     * class resolver needs config to know where to look.
     *
     * Both entries are accepted for 1.x. Splitting them needs classes moved
     * between namespaces, which is a BC break for a public library.
     *
     * @var list<string>
     */
    private const ALLOWED_NAMESPACE_CYCLES = [
        // One component covering most of Gacela\Framework. It is the package-level
        // shadow of the class cycle allow-listed above, widened by two things that
        // are cyclic only at namespace granularity:
        //   - the module-facing API (AbstractFacade/Factory/Config/Provider) and the
        //     resolvers that construct it — each base class names its resolver, and
        //     each resolver returns the matching base class;
        //   - the #[Provides] attribute scanner, which reads provider objects that
        //     the framework root defines.
        //
        // Cutting the class cycle above shrinks this but does not remove it: a
        // namespace pair is cyclic as soon as any one class on each side points at
        // the other, so a framework that configures itself is cyclic here almost by
        // construction. Splitting it needs classes moved between namespaces, which
        // is a BC break on a public library. Accepted for 1.x.
        'Gacela\Framework'
            . ' | Gacela\Framework\Attribute'
            . ' | Gacela\Framework\Bootstrap'
            . ' | Gacela\Framework\Bootstrap\Setup'
            . ' | Gacela\Framework\ClassResolver'
            . ' | Gacela\Framework\ClassResolver\Cache'
            . ' | Gacela\Framework\ClassResolver\ClassNameFinder'
            . ' | Gacela\Framework\ClassResolver\ClassNameFinder\Rule'
            . ' | Gacela\Framework\ClassResolver\Config'
            . ' | Gacela\Framework\ClassResolver\DocBlockService'
            . ' | Gacela\Framework\ClassResolver\Facade'
            . ' | Gacela\Framework\ClassResolver\Factory'
            . ' | Gacela\Framework\ClassResolver\GlobalInstance'
            . ' | Gacela\Framework\ClassResolver\Provider'
            . ' | Gacela\Framework\Config'
            . ' | Gacela\Framework\Config\ConfigReader'
            . ' | Gacela\Framework\Config\GacelaConfigBuilder'
            . ' | Gacela\Framework\Config\GacelaFileConfig'
            . ' | Gacela\Framework\Config\GacelaFileConfig\Factory'
            . ' | Gacela\Framework\Config\PathNormalizer'
            . ' | Gacela\Framework\Container'
            . ' | Gacela\Framework\Event\ClassResolver'
            . ' | Gacela\Framework\Event\ClassResolver\ClassNameFinder'
            . ' | Gacela\Framework\Health'
            . ' | Gacela\Framework\ServiceResolver',

        // The console module's own shape: ConsoleProvider registers the commands,
        // and each command calls back into ConsoleFacade. Cohesive by design --
        // it is one module, and this is the pattern Gacela teaches.
        //
        // It appeared the moment the commands moved from `@method ConsoleFacade
        // getFacade()` to `#[ServiceMap(className: ConsoleFacade::class)]`, but it
        // is not new: this graph builds edges from real AST nodes, and a docblock
        // is not one. The dependency always existed; declaring it made it visible.
        // That is the argument for the attribute, not against it -- an analyser
        // that cannot see a dependency cannot check it either.
        'Gacela\Console'
            . ' | Gacela\Console\Infrastructure'
            . ' | Gacela\Console\Infrastructure\Command',
    ];

    public function test_source_tree_has_no_unexpected_class_cycles(): void
    {
        $this->assertNoUnexpectedCycles(DependencyGraph::fromDirectory(self::SRC)->classCycles(), self::ALLOWED_CLASS_CYCLES, 'class');
    }

    public function test_source_tree_has_no_unexpected_namespace_cycles(): void
    {
        $this->assertNoUnexpectedCycles(DependencyGraph::fromDirectory(self::SRC)->namespaceCycles(), self::ALLOWED_NAMESPACE_CYCLES, 'namespace');
    }

    public function test_allow_listed_class_cycles_still_exist(): void
    {
        $this->assertAllowListIsNotStale(DependencyGraph::fromDirectory(self::SRC)->classCycles(), self::ALLOWED_CLASS_CYCLES, 'ALLOWED_CLASS_CYCLES');
    }

    public function test_allow_listed_namespace_cycles_still_exist(): void
    {
        $this->assertAllowListIsNotStale(DependencyGraph::fromDirectory(self::SRC)->namespaceCycles(), self::ALLOWED_NAMESPACE_CYCLES, 'ALLOWED_NAMESPACE_CYCLES');
    }

    /**
     * @param list<string> $found
     * @param list<string> $allowed
     */
    private function assertNoUnexpectedCycles(array $found, array $allowed, string $level): void
    {
        $unexpected = array_values(array_diff($found, $allowed));

        self::assertSame(
            [],
            $unexpected,
            sprintf(
                "Unexpected %s dependency cycle(s) introduced:\n- %s\n\n"
                . 'Break the cycle, or (if genuinely cohesive) add it to the allow-list with a rationale.',
                $level,
                implode("\n- ", $unexpected),
            ),
        );
    }

    /**
     * A cycle that no longer exists must leave the allow-list, otherwise the list
     * grows stale and starts forgiving components nobody has looked at in years.
     *
     * @param list<string> $found
     * @param list<string> $allowed
     */
    private function assertAllowListIsNotStale(array $found, array $allowed, string $constant): void
    {
        foreach ($allowed as $cycle) {
            self::assertContains(
                $cycle,
                $found,
                sprintf('Cycle no longer exists; remove it from %s: %s', $constant, $cycle),
            );
        }
    }
}
