<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Testing;

use Gacela\Framework\AbstractFacade;
use Gacela\Testing\ContractTestCase;
use Gacela\Testing\TContractMethod;

final class ContractTestCaseTest extends ContractTestCase
{
    public function test_assert_facade_contract_with_valid_contract(): void
    {
        $expectedMethods = [
            new TContractMethod(
                name: 'executeTask',
                parameters: ['taskId'],
                returnType: 'void',
            ),
            new TContractMethod(
                name: 'getResult',
                parameters: [],
                returnType: 'string',
            ),
        ];

        self::assertFacadeContract(SampleContractFacade::class, $expectedMethods);
    }

    public function test_assert_no_methods_removed(): void
    {
        self::assertNoMethodsRemoved(
            SampleContractFacade::class,
            ['executeTask', 'getResult'],
        );
    }

    public function test_assert_method_is_public(): void
    {
        self::assertMethodIsPublic(SampleContractFacade::class, 'executeTask');
        self::assertMethodIsPublic(SampleContractFacade::class, 'getResult');
    }

    public function test_contract_validation_detects_return_type_changes(): void
    {
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/incorrect return type/');

        $expectedMethods = [
            new TContractMethod(
                name: 'getResult',
                parameters: [],
                returnType: 'int', // Wrong return type
            ),
        ];

        self::assertFacadeContract(SampleContractFacade::class, $expectedMethods);
    }

    public function test_contract_validation_detects_parameter_count_changes(): void
    {
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/incorrect parameter count/');

        $expectedMethods = [
            new TContractMethod(
                name: 'executeTask',
                parameters: ['taskId', 'extraParam'], // Too many parameters
                returnType: 'void',
            ),
        ];

        self::assertFacadeContract(SampleContractFacade::class, $expectedMethods);
    }

    public function test_contract_validation_detects_missing_methods(): void
    {
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        $expectedMethods = [
            new TContractMethod(
                name: 'nonExistentMethod',
                parameters: [],
                returnType: 'void',
            ),
        ];

        self::assertFacadeContract(SampleContractFacade::class, $expectedMethods);
    }
}

final class SampleContractFacade extends AbstractFacade
{
    public function executeTask(string $taskId): void
    {
        // Implementation
    }

    public function getResult(): string
    {
        return 'result';
    }

    private function internalMethod(): void
    {
        // This should not be included in contract validation
    }
}
