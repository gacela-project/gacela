<?php

declare(strict_types=1);

namespace Gacela\Testing;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use function count;
use function sprintf;

abstract class ContractTestCase extends TestCase
{
    /**
     * Assert that a facade maintains its contract (method signatures).
     *
     * @param class-string $facadeClass
     * @param list<TContractMethod> $expectedMethods
     */
    public static function assertFacadeContract(string $facadeClass, array $expectedMethods): void
    {
        self::assertTrue(
            class_exists($facadeClass),
            sprintf('Facade class "%s" does not exist', $facadeClass),
        );

        $reflection = new ReflectionClass($facadeClass);
        $actualMethods = self::extractPublicMethods($reflection);

        foreach ($expectedMethods as $expectedMethod) {
            self::assertMethodExists($facadeClass, $expectedMethod->name, $actualMethods);
            self::assertMethodSignature($facadeClass, $expectedMethod, $actualMethods[$expectedMethod->name] ?? null);
        }
    }

    /**
     * Assert that no methods were removed (breaking changes).
     *
     * @param class-string $facadeClass
     * @param list<non-empty-string> $methodNames
     */
    public static function assertNoMethodsRemoved(string $facadeClass, array $methodNames): void
    {
        $reflection = new ReflectionClass($facadeClass);
        $actualMethods = self::extractPublicMethods($reflection);

        foreach ($methodNames as $methodName) {
            self::assertArrayHasKey(
                $methodName,
                $actualMethods,
                sprintf(
                    'Breaking change detected: Method "%s::%s()" was removed',
                    $facadeClass,
                    $methodName,
                ),
            );
        }
    }

    /**
     * Assert that method visibility hasn't changed to more restrictive.
     *
     * @param class-string $facadeClass
     * @param non-empty-string $methodName
     */
    public static function assertMethodIsPublic(string $facadeClass, string $methodName): void
    {
        $reflection = new ReflectionClass($facadeClass);

        self::assertTrue(
            $reflection->hasMethod($methodName),
            sprintf('Method "%s::%s()" does not exist', $facadeClass, $methodName),
        );

        $method = $reflection->getMethod($methodName);

        self::assertTrue(
            $method->isPublic(),
            sprintf(
                'Breaking change detected: Method "%s::%s()" is not public',
                $facadeClass,
                $methodName,
            ),
        );
    }

    /**
     * Assert that a method exists in the facade.
     *
     * @param class-string $facadeClass
     * @param non-empty-string $methodName
     * @param array<non-empty-string, ReflectionMethod> $actualMethods
     */
    private static function assertMethodExists(string $facadeClass, string $methodName, array $actualMethods): void
    {
        self::assertArrayHasKey(
            $methodName,
            $actualMethods,
            sprintf('Method "%s::%s()" does not exist or is not public', $facadeClass, $methodName),
        );
    }

    /**
     * Assert that a method signature matches the expected contract.
     *
     * @param class-string $facadeClass
     */
    private static function assertMethodSignature(
        string $facadeClass,
        TContractMethod $expectedMethod,
        ?ReflectionMethod $actualMethod,
    ): void {
        if ($actualMethod === null) {
            self::fail(sprintf('Method "%s::%s()" does not exist', $facadeClass, $expectedMethod->name));

            return;
        }

        // Check return type
        $actualReturnType = $actualMethod->getReturnType();
        $actualReturnTypeName = $actualReturnType instanceof ReflectionNamedType
            ? $actualReturnType->getName()
            : null;

        if ($expectedMethod->returnType !== null) {
            self::assertSame(
                $expectedMethod->returnType,
                $actualReturnTypeName,
                sprintf(
                    'Method "%s::%s()" has incorrect return type. Expected: %s, Actual: %s',
                    $facadeClass,
                    $expectedMethod->name,
                    $expectedMethod->returnType,
                    $actualReturnTypeName ?? 'none',
                ),
            );
        }

        // Check parameter count
        $actualParams = $actualMethod->getParameters();
        self::assertCount(
            count($expectedMethod->parameters),
            $actualParams,
            sprintf(
                'Method "%s::%s()" has incorrect parameter count. Expected: %d, Actual: %d',
                $facadeClass,
                $expectedMethod->name,
                count($expectedMethod->parameters),
                count($actualParams),
            ),
        );
    }

    /**
     * Extract all public methods from a class reflection.
     *
     * @return array<non-empty-string, ReflectionMethod>
     */
    private static function extractPublicMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip methods from parent classes and magic methods
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            $methods[$method->getName()] = $method;
        }

        return $methods;
    }
}
