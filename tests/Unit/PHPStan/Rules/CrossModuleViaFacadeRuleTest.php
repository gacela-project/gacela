<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\PHPStan\Rules\CrossModuleViaFacadeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionMethod;

/**
 * @extends RuleTestCase<CrossModuleViaFacadeRule>
 */
final class CrossModuleViaFacadeRuleTest extends RuleTestCase
{
    private const ROOT = 'GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule';

    private string $rootNamespace = self::ROOT;

    private int $modulePathSegments = 1;

    /** @var list<string> */
    private array $sharedNamespaces = [];

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

    public function test_skips_every_allowed_reference_kind_and_reports_each_distinct_violation(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/User/MixedOrderFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\MixedOrderFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopRepository from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop). Cross-module access must go through a Facade.',
                    15,
                ],
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\MixedOrderFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop). Cross-module access must go through a Facade.',
                    15,
                ],
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\MixedOrderFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopWriter from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop). Cross-module access must go through a Facade.',
                    15,
                ],
            ],
        );
    }

    public function test_default_module_path_segments_is_one(): void
    {
        $rule = new CrossModuleViaFacadeRule(self::ROOT);
        $moduleOf = new ReflectionMethod($rule, 'moduleOf');

        self::assertSame(self::ROOT . '\Shop', $moduleOf->invoke($rule, self::ROOT . '\Shop\Domain\ShopService'));
        self::assertNull($moduleOf->invoke($rule, self::ROOT . '\OnlyOneSegment'));
    }

    public function test_reports_shared_namespace_reference_when_not_allowlisted(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/User/SharedKernelFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\SharedKernelFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shared\Clock from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shared). Cross-module access must go through a Facade.',
                    9,
                ],
            ],
        );
    }

    public function test_allows_reference_into_a_shared_namespace(): void
    {
        $this->sharedNamespaces = [self::ROOT . '\Shared'];

        $this->analyse([__DIR__ . '/Fixture/CrossModule/User/SharedKernelFactory.php'], []);
    }

    public function test_shared_namespace_must_match_a_namespace_boundary(): void
    {
        // "Shar" is a prefix of "Shared" but not a namespace boundary:
        // the reference must still be reported.
        $this->sharedNamespaces = [self::ROOT . '\Shar'];

        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/User/SharedKernelFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\SharedKernelFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shared\Clock from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shared). Cross-module access must go through a Facade.',
                    9,
                ],
            ],
        );
    }

    public function test_still_reports_a_violation_after_an_allowed_shared_reference(): void
    {
        $this->sharedNamespaces = [self::ROOT . '\Shared'];

        $this->analyse(
            [__DIR__ . '/Fixture/CrossModule/User/SharedThenBadFactory.php'],
            [
                [
                    'Class GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User\SharedThenBadFactory references GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopService from another module (GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop). Cross-module access must go through a Facade.',
                    10,
                ],
            ],
        );
    }

    public function test_class_inside_a_shared_namespace_is_not_itself_checked(): void
    {
        $this->sharedNamespaces = [self::ROOT . '\Shared'];

        $this->analyse([__DIR__ . '/Fixture/CrossModule/Shared/UsesOtherModule.php'], []);
    }

    protected function getRule(): Rule
    {
        return new CrossModuleViaFacadeRule($this->rootNamespace, $this->modulePathSegments, $this->sharedNamespaces);
    }
}
