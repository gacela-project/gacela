<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Debug;

use Gacela\Console\Application\Debug\ConstructorInspector;
use Gacela\Console\Application\Debug\ParameterStatus;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\AutowirableCollaborator;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\BoundContract;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\BoundImplementation;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\FrameworkInjectService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\InjectService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\MixedDependenciesService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\NoConstructorService;
use GacelaTest\Feature\Console\DebugDependencies\Fixtures\UntypedAndUnionService;
use PHPUnit\Framework\TestCase;

final class ConstructorInspectorTest extends TestCase
{
    private ConstructorInspector $inspector;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(BoundContract::class, BoundImplementation::class);
        });

        $this->inspector = new ConstructorInspector();
    }

    /**
     * The detail is one line in a report that puts one parameter per line, and
     * in a JSON string field. `var_export()` on an array default put line
     * breaks through both.
     */
    public function test_an_array_default_is_described_on_one_line(): void
    {
        $inspection = (new ConstructorInspector())->inspect(WithArrayDefaults::class);

        $details = [];
        foreach ($inspection->parameters as $parameter) {
            $details[$parameter->name] = $parameter->detail;
        }

        self::assertSame("= ['a' => 1, 'b' => ['c' => 2]]", $details['$nested']);
        self::assertSame('= []', $details['$empty']);
        self::assertSame("= ['eur', 'usd']", $details['$list']);
    }

    public function test_class_without_constructor(): void
    {
        $inspection = $this->inspector->inspect(NoConstructorService::class);

        self::assertFalse($inspection->hasConstructor);
        self::assertSame([], $inspection->parameters);
        self::assertTrue($inspection->isFullyResolvable());
    }

    public function test_untyped_union_and_missing_types_are_categorized(): void
    {
        $inspection = $this->inspector->inspect(UntypedAndUnionService::class);

        self::assertTrue($inspection->hasConstructor);
        self::assertCount(3, $inspection->parameters);

        self::assertSame(ParameterStatus::NoTypeHint, $inspection->parameters[0]->status);
        self::assertSame(ParameterStatus::UnsupportedType, $inspection->parameters[1]->status);
        self::assertSame(ParameterStatus::MissingType, $inspection->parameters[2]->status);
    }

    public function test_every_type_shape_is_rendered_the_way_php_writes_it(): void
    {
        $inspection = $this->inspector->inspect(UntypedAndUnionService::class);

        self::assertSame('mixed', $inspection->parameters[0]->renderedType, 'an untyped parameter reads as mixed');
        self::assertSame('string|int', $inspection->parameters[1]->renderedType);
        self::assertSame('Totally\\Missing\\Type', $inspection->parameters[2]->renderedType);
    }

    public function test_a_nullable_type_keeps_its_question_mark(): void
    {
        $inspection = $this->inspector->inspect(MixedDependenciesService::class);

        self::assertSame('?' . AutowirableCollaborator::class, $inspection->parameters[5]->renderedType);
    }

    public function test_mixed_dependencies_are_categorized(): void
    {
        $inspection = $this->inspector->inspect(MixedDependenciesService::class);

        self::assertTrue($inspection->hasConstructor);
        self::assertCount(6, $inspection->parameters);

        $statuses = [];
        foreach ($inspection->parameters as $parameter) {
            $statuses[$parameter->name] = $parameter->status;
        }

        self::assertSame(ParameterStatus::Bound, $statuses['$bound']);
        self::assertSame(ParameterStatus::Autowirable, $statuses['$collaborator']);
        self::assertSame(ParameterStatus::UnboundInterface, $statuses['$unbound']);
        self::assertSame(ParameterStatus::ScalarWithoutDefault, $statuses['$mandatoryScalar']);
        self::assertSame(ParameterStatus::HasDefault, $statuses['$optionalScalar']);
        self::assertSame(ParameterStatus::Autowirable, $statuses['$nullableCollaborator']);

        self::assertSame(4, $inspection->resolvableCount());
        self::assertSame(2, $inspection->unresolvableCount());
        self::assertFalse($inspection->isFullyResolvable());
    }

    public function test_bound_parameter_includes_target(): void
    {
        $inspection = $this->inspector->inspect(MixedDependenciesService::class);

        $bound = $inspection->parameters[0];
        self::assertSame('$bound', $bound->name);
        self::assertSame('bound -> ' . BoundImplementation::class, $bound->detail);
    }

    public function test_autowirable_parameter_details(): void
    {
        $inspection = $this->inspector->inspect(MixedDependenciesService::class);

        $collaborator = $inspection->parameters[1];
        self::assertSame('$collaborator', $collaborator->name);
        self::assertSame(AutowirableCollaborator::class, $collaborator->renderedType);
        self::assertSame('autowirable', $collaborator->detail);
    }

    public function test_inject_parameter_is_flagged(): void
    {
        $inspection = $this->inspector->inspect(InjectService::class);

        self::assertCount(2, $inspection->parameters);
        self::assertSame(ParameterStatus::Inject, $inspection->parameters[0]->status);
        self::assertSame('inject', $inspection->parameters[0]->detail);
        self::assertTrue($inspection->parameters[0]->isResolvable());
    }

    public function test_inject_parameter_with_override_shows_implementation(): void
    {
        $inspection = $this->inspector->inspect(InjectService::class);

        $override = $inspection->parameters[1];
        self::assertSame(ParameterStatus::Inject, $override->status);
        self::assertSame('inject -> ' . BoundImplementation::class, $override->detail);
    }

    /**
     * `Gacela\Framework\Attribute\Inject` subclasses the container's attribute,
     * and both imports are supported. Reading for an exact FQN would report the
     * parameter as plain autowiring, which is the silent failure the attribute's
     * own docblock warns about.
     */
    public function test_the_framework_spelling_of_inject_is_flagged_too(): void
    {
        $inspection = $this->inspector->inspect(FrameworkInjectService::class);

        self::assertCount(2, $inspection->parameters);
        self::assertSame(ParameterStatus::Inject, $inspection->parameters[0]->status);
        self::assertSame('inject', $inspection->parameters[0]->detail);
        self::assertSame(ParameterStatus::Inject, $inspection->parameters[1]->status);
        self::assertSame('inject -> ' . BoundImplementation::class, $inspection->parameters[1]->detail);
    }

    public function test_a_binding_to_an_already_built_object_names_its_class(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(BoundContract::class, new BoundImplementation());
        });

        $inspection = (new ConstructorInspector())->inspect(MixedDependenciesService::class);

        self::assertSame(
            'bound -> ' . BoundImplementation::class . ' instance',
            $inspection->parameters[0]->detail,
        );
    }
}

final class WithArrayDefaults
{
    /**
     * @param array<string, mixed> $nested
     * @param array<array-key, mixed> $empty
     * @param list<string> $list
     */
    public function __construct(
        public readonly array $nested = ['a' => 1, 'b' => ['c' => 2]],
        public readonly array $empty = [],
        public readonly array $list = ['eur', 'usd'],
    ) {
    }
}
