<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\PHPStan\Rules\CrossModuleViaFacadeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<CrossModuleViaFacadeRule>
 */
final class CrossModuleViaFacadeRuleTest extends RuleTestCase
{
    private const ROOT = 'GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule';

    private string $rootNamespace = self::ROOT;

    private int $modulePathSegments = 1;

    public function test_reports_cross_module_new_of_non_facade(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/User/BadNewFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\BadNewFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop). Cross-module access must go through a Facade.',
                    9,
                ],
            ],
        );
    }

    public function test_allows_cross_module_facade_reference(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModule/User/FacadeFactory.php'], []);
    }

    public function test_allows_same_module_reference(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModule/User/SameModuleFactory.php'], []);
    }

    public function test_ignores_classes_outside_root_namespace(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModule/User/OutsideRootFactory.php'], []);
    }

    public function test_skips_when_current_class_is_outside_root_namespace(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModuleOutsideRoot/SomeFactory.php'], []);
    }

    public function test_reports_static_call_into_another_module(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/User/StaticCallFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\StaticCallFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop). Cross-module access must go through a Facade.',
                    9,
                ],
            ],
        );
    }

    public function test_reports_class_const_fetch_into_another_module(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/User/ClassConstFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\ClassConstFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop). Cross-module access must go through a Facade.',
                    9,
                ],
            ],
        );
    }

    public function test_deduplicates_repeated_violations(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/User/DuplicateFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\DuplicateFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop). Cross-module access must go through a Facade.',
                    9,
                ],
            ],
        );
    }

    public function test_respects_module_path_segments_setting(): void
    {
        $this->modulePathSegments = 2;

        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/Admin/User/AdminUserFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Admin\User\AdminUserFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Admin\Shop\AdminShopService from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Admin\Shop). Cross-module access must go through a Facade.',
                    9,
                ],
            ],
        );
    }

    public function test_same_first_segment_is_same_module_when_depth_is_one(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModule/Admin/User/AdminUserFactory.php'], []);
    }

    public function test_ignores_references_with_no_module_depth(): void
    {
        $this->analyse([__DIR__ . '/Fixture/CrossModule/User/NoDepthRefFactory.php'], []);
    }

    protected function getRule(): Rule
    {
        return new CrossModuleViaFacadeRule($this->rootNamespace, $this->modulePathSegments);
    }
}
