<?php

declare(strict_types=1);

namespace Gacela\Testing;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Gacela;
use Gacela\Framework\ModuleDependenciesInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use ReflectionNamedType;
use Throwable;

use function array_diff;
use function array_key_exists;
use function array_map;
use function array_slice;
use function class_exists;
use function interface_exists;
use function sprintf;

abstract class GacelaTestCase extends TestCase
{
    /**
     * Assert that a module only depends on the explicitly declared dependencies.
     *
     * @param class-string<AbstractFacade> $facadeClass
     * @param list<class-string<AbstractFacade>> $expectedDependencies
     */
    public static function assertModuleDependencies(string $facadeClass, array $expectedDependencies): void
    {
        self::assertTrue(
            class_exists($facadeClass),
            sprintf('Facade class "%s" does not exist', $facadeClass),
        );

        $facade = new $facadeClass();

        self::assertInstanceOf(
            AbstractFacade::class,
            $facade,
            sprintf('Class "%s" must extend AbstractFacade', $facadeClass),
        );

        /**
         * @psalm-suppress RedundantConditionGivenDocblockType
         * @psalm-suppress DocblockTypeContradiction
         */
        $actualDependencies = $facade instanceof ModuleDependenciesInterface
            ? $facade->dependencies()
            : [];

        $missingDependencies = array_diff($expectedDependencies, $actualDependencies);
        $extraDependencies = array_diff($actualDependencies, $expectedDependencies);

        self::assertEmpty(
            $missingDependencies,
            sprintf(
                'Expected dependencies not declared in %s::dependencies(): %s',
                $facadeClass,
                implode(', ', $missingDependencies),
            ),
        );

        self::assertEmpty(
            $extraDependencies,
            sprintf(
                'Unexpected dependencies declared in %s::dependencies(): %s',
                $facadeClass,
                implode(', ', $extraDependencies),
            ),
        );
    }

    /**
     * Assert that a class does not directly depend on another module's internals.
     *
     * @param class-string $className
     * @param non-empty-string $forbiddenNamespace
     */
    public static function assertNoDirectDependencyOn(string $className, string $forbiddenNamespace): void
    {
        self::assertTrue(
            class_exists($className) || interface_exists($className),
            sprintf('Class "%s" does not exist', $className),
        );

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            self::assertTrue(true);

            return;
        }

        $violations = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramType = $parameter->getType();

            if ($paramType === null) {
                continue;
            }

            /** @var class-string|null $paramClassName */
            $paramClassName = $paramType instanceof ReflectionNamedType ? $paramType->getName() : null;

            if ($paramClassName === null) {
                continue;
            }

            if (str_starts_with($paramClassName, $forbiddenNamespace)) {
                $violations[] = sprintf(
                    'Parameter $%s has type %s',
                    $parameter->getName(),
                    $paramClassName,
                );
            }
        }

        self::assertEmpty(
            $violations,
            sprintf(
                'Class "%s" has direct dependencies on forbidden namespace "%s": %s',
                $className,
                $forbiddenNamespace,
                implode(', ', $violations),
            ),
        );
    }

    /**
     * Assert that the container can resolve a service without errors.
     *
     * @param class-string $serviceClass
     */
    public static function assertContainerCanResolve(string $serviceClass): void
    {
        $container = Gacela::container();

        try {
            /** @psalm-suppress MixedAssignment */
            $service = $container->get($serviceClass);

            self::assertInstanceOf(
                $serviceClass,
                $service,
                sprintf('Container returned wrong instance for "%s"', $serviceClass),
            );
        } catch (Throwable $throwable) {
            self::fail(
                sprintf(
                    'Container failed to resolve "%s": %s',
                    $serviceClass,
                    $throwable->getMessage(),
                ),
            );
        }
    }

    /**
     * Assert that a module facade can be instantiated and has required methods.
     *
     * @param class-string<AbstractFacade> $facadeClass
     * @param list<non-empty-string> $requiredMethods
     */
    public static function assertFacadeHasMethods(string $facadeClass, array $requiredMethods): void
    {
        self::assertTrue(
            class_exists($facadeClass),
            sprintf('Facade class "%s" does not exist', $facadeClass),
        );

        $reflection = new ReflectionClass($facadeClass);

        $missingMethods = [];

        foreach ($requiredMethods as $method) {
            if (!$reflection->hasMethod($method)) {
                $missingMethods[] = $method;
            }
        }

        self::assertEmpty(
            $missingMethods,
            sprintf(
                'Facade "%s" is missing required methods: %s',
                $facadeClass,
                implode(', ', $missingMethods),
            ),
        );
    }

    /**
     * Assert that a module is properly structured with expected components.
     *
     * @param non-empty-string $moduleNamespace
     * @param array<string, bool> $expectedComponents ['Facade' => true, 'Factory' => true, 'Config' => false, 'Provider' => false]
     */
    public static function assertModuleStructure(string $moduleNamespace, array $expectedComponents): void
    {
        $componentTypes = ['Facade', 'Factory', 'Config', 'Provider'];

        foreach ($componentTypes as $componentType) {
            $className = $moduleNamespace . '\\' . $componentType;
            $shouldExist = $expectedComponents[$componentType] ?? false;

            if ($shouldExist) {
                self::assertTrue(
                    class_exists($className),
                    sprintf('Expected component "%s" does not exist', $className),
                );
            }
        }
    }

    /**
     * Assert that no circular dependencies exist between modules.
     *
     * @param list<class-string<AbstractFacade>> $facadeClasses
     */
    public static function assertNoCircularDependencies(array $facadeClasses): void
    {
        /** @var array<class-string, list<class-string>> $dependencyGraph */
        $dependencyGraph = [];

        foreach ($facadeClasses as $facadeClass) {
            if (!class_exists($facadeClass)) {
                continue;
            }

            $facade = new $facadeClass();

            /**
             * @psalm-suppress RedundantConditionGivenDocblockType
             * @psalm-suppress TypeDoesNotContainType
             */
            $dependencies = $facade instanceof ModuleDependenciesInterface
                ? $facade->dependencies()
                : [];

            $dependencyGraph[$facadeClass] = $dependencies;
        }

        $violations = self::findCircularDependencies($dependencyGraph);

        self::assertEmpty(
            $violations,
            sprintf(
                'Circular dependencies detected: %s',
                implode('; ', array_map(
                    static fn (array $cycle): string => implode(' -> ', $cycle),
                    $violations,
                )),
            ),
        );
    }

    /**
     * @param array<class-string, list<class-string>> $graph
     *
     * @return list<list<class-string>>
     */
    private static function findCircularDependencies(array $graph): array
    {
        $violations = [];
        $visited = [];
        $recursionStack = [];

        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $cycle = self::detectCycle($node, $graph, $visited, $recursionStack, []);

                if ($cycle !== []) {
                    $violations[] = $cycle;
                }
            }
        }

        return $violations;
    }

    /**
     * @param class-string $node
     * @param array<class-string, list<class-string>> $graph
     * @param array<class-string, bool> $visited
     * @param array<class-string, bool> $recursionStack
     * @param list<class-string> $path
     *
     * @return list<class-string>
     */
    private static function detectCycle(
        string $node,
        array $graph,
        array &$visited,
        array &$recursionStack,
        array $path,
    ): array {
        $visited[$node] = true;
        $recursionStack[$node] = true;
        $path[] = $node;

        if (array_key_exists($node, $graph)) {
            foreach ($graph[$node] as $dependency) {
                if (!isset($visited[$dependency])) {
                    $cycle = self::detectCycle($dependency, $graph, $visited, $recursionStack, $path);

                    if ($cycle !== []) {
                        return $cycle;
                    }
                } elseif (isset($recursionStack[$dependency])) {
                    // Found a cycle
                    $cycleStart = array_search($dependency, $path, true);

                    return $cycleStart !== false ? array_slice($path, $cycleStart) : [];
                }
            }
        }

        unset($recursionStack[$node]);

        return [];
    }
}
