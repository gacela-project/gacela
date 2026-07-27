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
            self::classNames($inspection),
        );
    }

    public function test_a_bound_interface_is_reported_as_its_concrete_target(): void
    {
        $inspection = $this->inspector->inspect(NestedRootService::class);

        // The container resolves the binding before walking further, so the tree
        // names what will actually be built -- BoundContract never appears.
        self::assertContains(BoundImplementation::class, self::classNames($inspection));
        self::assertNotContains(BoundContract::class, self::classNames($inspection));
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
        $before = self::statusIn($this->inspector->inspect(NestedRootService::class), AutowirableCollaborator::class);
        self::assertSame(ProvisionStatus::Autowired, $before);

        Gacela::container()->set(AutowirableCollaborator::class, new AutowirableCollaborator());

        // provides() is what tells the two apart: has() was already true of both.
        self::assertSame(ProvisionStatus::Instance, self::statusIn(
            $this->inspector->inspect(NestedRootService::class),
            AutowirableCollaborator::class,
        ));
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

        self::assertSame(ProvisionStatus::Binding, self::statusIn($inspection, UnboundContract::class));
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
    private static function classNames(DependencyTreeInspection $inspection): array
    {
        return array_map(
            static fn (object $node): string => $node->className,
            $inspection->nodes,
        );
    }

    private static function statusIn(DependencyTreeInspection $inspection, string $className): ProvisionStatus
    {
        foreach ($inspection->nodes as $node) {
            if ($node->className === $className) {
                return $node->status;
            }
        }

        self::fail($className . ' is not part of the tree');
    }
}
