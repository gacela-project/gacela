<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Testing;

use Gacela\Testing\ContractTestCase;
use Gacela\Testing\TContractMethod;

/**
 * Example contract test demonstrating how to prevent breaking changes in facades.
 *
 * Contract tests ensure that public APIs remain stable across versions,
 * preventing accidental breaking changes during refactoring or updates.
 *
 * Use cases:
 * - Ensure backward compatibility during refactoring
 * - Detect breaking changes before release
 * - Document the expected public API
 * - Prevent accidental removal of public methods
 */
final class FacadeContractTestExample extends ContractTestCase
{
    /**
     * Example: Define and verify the complete facade contract.
     *
     * This test documents all public methods and their signatures,
     * ensuring they remain stable across versions.
     */
    public function test_facade_maintains_contract(): void
    {
        // For demonstration purposes, we'll just assert true
        self::assertTrue(true, 'Replace with actual facade contract');

        // Example usage (commented out):
        // $expectedContract = [
        //     new TContractMethod(
        //         name: 'createUser',
        //         parameters: ['username', 'email', 'password'],
        //         returnType: 'int' // Returns user ID
        //     ),
        //     new TContractMethod(
        //         name: 'findUserById',
        //         parameters: ['userId'],
        //         returnType: 'array' // Returns user data
        //     ),
        //     new TContractMethod(
        //         name: 'updateUser',
        //         parameters: ['userId', 'data'],
        //         returnType: 'bool' // Returns success status
        //     ),
        //     new TContractMethod(
        //         name: 'deleteUser',
        //         parameters: ['userId'],
        //         returnType: 'void'
        //     ),
        // ];
        //
        // self::assertFacadeContract(UserFacade::class, $expectedContract);
    }

    /**
     * Example: Verify that critical methods haven't been removed.
     *
     * This is a lighter-weight alternative to full contract testing
     * when you only want to ensure specific methods still exist.
     */
    public function test_critical_methods_are_not_removed(): void
    {
        // For demonstration purposes, we'll just assert true
        self::assertTrue(true, 'Replace with actual critical methods');

        // Example usage (commented out):
        // self::assertNoMethodsRemoved(
        //     UserFacade::class,
        //     [
        //         'createUser',
        //         'findUserById',
        //         'updateUser',
        //         'deleteUser',
        //     ]
        // );
    }

    /**
     * Example: Verify that a method is still public.
     *
     * Useful when you want to ensure that method visibility
     * hasn't been accidentally changed to protected/private.
     */
    public function test_public_api_methods_remain_accessible(): void
    {
        // For demonstration purposes, we'll just assert true
        self::assertTrue(true, 'Replace with actual public methods');

        // Example usage (commented out):
        // self::assertMethodIsPublic(UserFacade::class, 'createUser');
        // self::assertMethodIsPublic(UserFacade::class, 'findUserById');
    }

    /**
     * Example: Test a specific critical method's signature.
     *
     * Useful for high-stakes methods where you want to ensure
     * the signature remains exactly as expected.
     */
    public function test_critical_method_signature_is_stable(): void
    {
        // For demonstration purposes, we'll just assert true
        self::assertTrue(true, 'Replace with actual critical method');

        // Example usage (commented out):
        // $createUserContract = [
        //     new TContractMethod(
        //         name: 'createUser',
        //         parameters: ['username', 'email', 'password'],
        //         returnType: 'int'
        //     ),
        // ];
        //
        // self::assertFacadeContract(UserFacade::class, $createUserContract);
    }

    /**
     * Best practices for contract testing:
     *
     * 1. Run contract tests in CI/CD to catch breaking changes early
     * 2. Version your contracts - when you intentionally make breaking changes,
     *    update the contract and bump the major version
     * 3. Use contract tests alongside integration tests for comprehensive coverage
     * 4. Consider contract tests for all public-facing facades
     * 5. Document why each method is in the contract (business criticality)
     */
}
