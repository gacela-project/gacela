<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Debug;

use Gacela\Console\Application\Debug\DependencyTreeInspection;
use Gacela\Console\Application\Debug\DependencyTreeInspector;
use Gacela\Console\Application\Debug\ProvisionStatus;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\AutowirableCollaborator;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\BoundContract;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\BoundImplementation;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\CyclicLeftService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\CyclicRightService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\NestedMidService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\NestedRootService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\NoConstructorService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\SecondUnboundContract;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\TwoUnresolvableService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\UnboundContract;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_map;

final class DependencyTreeInspectorTest extends TestCase
{
    private DependencyTreeInspector $inspector;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(BoundContract::class, BoundImplementation::class);
        });

        $this->inspector = new DependencyTreeInspector();
    }

    public function test_a_class_without_dependencies_has_an_empty_tree(): void
    {
        $inspection = $this->inspector->inspect(NoConstructorService::class);

        self::assertTrue($inspection->containerAvailable);
        self::assertSame([], $inspection->nodes);
        self::assertTrue($inspection->isFullyProvided());
    }

    public function test_the_tree_reaches_past_the_first_level(): void
    {
        $inspection = $this->inspector->inspect(NestedRootService::class);

        // The constructor names only NestedMidService; the other three are its
        // dependencies, which the one-level view cannot report.
        self::assertSame(
            [
                NestedMidService::class,
                BoundImplementation::class,
                AutowirableCollaborator::class,
                UnboundContract::class,
            ],
            $this->classNames($inspection),
        );
    }

    /**
     * The flat list says a class is reached; the tree says by whom. A missing
     * dependency four levels down is only actionable with the second answer.
     */
    public function test_the_tree_keeps_the_shape_the_flat_list_throws_away(): void
    {
        $inspection = $this->inspector->inspect(NestedRootService::class);

        self::assertCount(1, $inspection->tree);

        $mid = $inspection->tree[0];
        self::assertSame(NestedMidService::class, $mid->className);
        self::assertSame('mid', $mid->parameter);

        self::assertSame(
            [BoundImplementation::class, AutowirableCollaborator::class, UnboundContract::class],
            array_map(static fn (object $node): string => $node->className, $mid->children),
        );
        self::assertSame(
            ['bound', 'collaborator', 'unbound'],
            array_map(static fn (object $node): string => $node->parameter, $mid->children),
        );
    }

    public function test_a_tree_node_carries_the_same_status_as_the_flat_list(): void
    {
        $inspection = $this->inspector->inspect(NestedRootService::class);

        $unbound = $inspection->tree[0]->children[2];

        self::assertSame(UnboundContract::class, $unbound->className);
        self::assertSame(ProvisionStatus::Unresolvable, $unbound->status);
        self::assertFalse($unbound->isProvided());
    }

    /**
     * A cycle is marked and cut, not thrown: inspecting a broken graph is
     * precisely when this command gets run.
     */
    public function test_a_constructor_cycle_is_marked_rather_than_recursed_forever(): void
    {
        $inspection = $this->inspector->inspect(CyclicLeftService::class);

        $right = $inspection->tree[0];
        self::assertSame(CyclicRightService::class, $right->className);
        self::assertFalse($right->repeated);

        $backToLeft = $right->children[0];
        self::assertSame(CyclicLeftService::class, $backToLeft->className);
        self::assertTrue($backToLeft->repeated);
        self::assertSame([], $backToLeft->children);
    }

    public function test_a_class_without_dependencies_has_an_empty_tree_branch(): void
    {
        self::assertSame([], $this->inspector->inspect(NoConstructorService::class)->tree);
    }

    public function test_a_bound_interface_is_reported_as_its_concrete_target(): void
    {
        $inspection = $this->inspector->inspect(NestedRootService::class);

        // The container resolves the binding before walking further, so the tree
        // names what will actually be built -- BoundContract never appears.
        self::assertContains(BoundImplementation::class, $this->classNames($inspection));
        self::assertNotContains(BoundContract::class, $this->classNames($inspection));
    }

    public function test_an_unbound_interface_is_the_only_unresolvable_node(): void
    {
        $inspection = $this->inspector->inspect(NestedRootService::class);

        self::assertSame([UnboundContract::class], array_map(
            static fn (object $node): string => $node->className,
            $inspection->unresolvableNodes(),
        ));
        self::assertFalse($inspection->isFullyProvided());
    }

    public function test_every_unresolvable_node_is_reported_not_only_the_first(): void
    {
        $inspection = $this->inspector->inspect(TwoUnresolvableService::class);

        self::assertSame(
            [UnboundContract::class, SecondUnboundContract::class],
            array_map(
                static fn (object $node): string => $node->className,
                $inspection->unresolvableNodes(),
            ),
        );
    }

    public function test_an_autowired_node_is_distinguished_from_a_stored_instance(): void
    {
        $before = $this->statusIn($this->inspector->inspect(NestedRootService::class), AutowirableCollaborator::class);
        self::assertSame(ProvisionStatus::Autowired, $before);

        Gacela::container()->set(AutowirableCollaborator::class, new AutowirableCollaborator());

        // provides() is what tells the two apart: has() was already true of both.
        self::assertSame(ProvisionStatus::Instance, $this->statusIn($this->inspector->inspect(NestedRootService::class), AutowirableCollaborator::class));
    }

    public function test_a_registered_binding_keeps_its_own_status(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            // A closure binding is not a class-string, so the tree keeps the
            // interface name and the binding stays visible on the node itself.
            $config->addBinding(UnboundContract::class, static fn (): object => new BoundImplementation());
            $config->addBinding(BoundContract::class, BoundImplementation::class);
        });

        $inspection = (new DependencyTreeInspector())->inspect(NestedMidService::class);

        self::assertSame(ProvisionStatus::Binding, $this->statusIn($inspection, UnboundContract::class));
        self::assertTrue($inspection->isFullyProvided());
    }

    public function test_an_unknown_class_yields_an_empty_tree_instead_of_throwing(): void
    {
        /** @var class-string $missing */
        $missing = 'Totally\\Missing\\Service';

        $inspection = $this->inspector->inspect($missing);

        self::assertSame($missing, $inspection->className);
        self::assertTrue($inspection->containerAvailable);
        self::assertSame([], $inspection->nodes);
    }

    public function test_without_a_bootstrapped_container_the_tree_is_reported_as_unavailable(): void
    {
        $gacelaProxy = new ReflectionClass(Gacela::class);
        $gacelaProxy->getProperty('mainContainer')->setValue($gacelaProxy, value: null);

        $inspection = $this->inspector->inspect(NestedRootService::class);

        // Unavailable is not the same answer as "no dependencies", and the
        // command renders the two differently.
        self::assertFalse($inspection->containerAvailable);
        self::assertSame([], $inspection->nodes);
    }

    /**
     * @return list<string>
     */
    private function classNames(DependencyTreeInspection $inspection): array
    {
        return array_map(
            static fn (object $node): string => $node->className,
            $inspection->nodes,
        );
    }

    private function statusIn(DependencyTreeInspection $inspection, string $className): ProvisionStatus
    {
        foreach ($inspection->nodes as $node) {
            if ($node->className === $className) {
                return $node->status;
            }
        }

        self::fail($className . ' is not part of the tree');
    }
}
