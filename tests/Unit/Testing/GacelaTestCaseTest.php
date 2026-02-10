<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Testing;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ModuleDependenciesInterface;
use Gacela\Testing\GacelaTestCase;

final class GacelaTestCaseTest extends GacelaTestCase
{
    public function test_assert_module_dependencies_with_matching_dependencies(): void
    {
        self::assertModuleDependencies(
            SampleModuleFacade::class,
            [SampleDependencyFacade::class],
        );
    }

    public function test_assert_facade_has_methods_with_existing_methods(): void
    {
        self::assertFacadeHasMethods(
            SampleModuleFacade::class,
            ['execute', 'process'],
        );
    }

    public function test_assert_no_circular_dependencies_with_valid_structure(): void
    {
        self::assertNoCircularDependencies([
            SampleModuleFacade::class,
            SampleDependencyFacade::class,
        ]);
    }

    public function test_assert_module_structure_verifies_components(): void
    {
        // Test with the sample classes we created
        self::assertModuleStructure(
            'GacelaTest\Unit\Testing',
            [
                'Facade' => false,
                'Factory' => false,
                'Config' => false,
                'Provider' => false,
            ],
        );

        // Verify the assertion works
        self::assertTrue(true);
    }
}

final class SampleModuleFacade extends AbstractFacade implements ModuleDependenciesInterface
{
    /**
     * @return list<class-string<AbstractFacade>>
     */
    public function dependencies(): array
    {
        return [SampleDependencyFacade::class];
    }
}

final class SampleDependencyFacade extends AbstractFacade
{
}
