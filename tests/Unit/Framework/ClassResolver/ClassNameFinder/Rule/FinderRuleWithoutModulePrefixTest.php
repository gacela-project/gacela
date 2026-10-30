<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithoutModulePrefix;
use PHPUnit\Framework\TestCase;

final class FinderRuleWithoutModulePrefixTest extends TestCase
{
    private FinderRuleWithoutModulePrefix $rule;

    protected function setUp(): void
    {
        $this->rule = new FinderRuleWithoutModulePrefix();
    }

    public function test_a_global_caller_has_no_synthetic_module_namespace(): void
    {
        self::assertSame(
            '\Factory',
            $this->rule->buildClassCandidate('', 'Factory', ClassInfo::from('RootFacade', 'Factory')),
        );
        self::assertSame('\Root\Factory', $this->rule->buildClassCandidate('', 'Factory', ClassInfo::from('Root\\RootFacade', 'Factory')));
    }

    public function test_build_without_project_namespace(): void
    {
        $projectNamespace = '';
        $resolvableType = 'Factory';
        $classInfo = ClassInfo::from($this);

        $actual = $this->rule->buildClassCandidate($projectNamespace, $resolvableType, $classInfo);

        self::assertSame('\Rule\Factory', $actual);
    }

    public function test_build_with_project_namespace(): void
    {
        $projectNamespace = 'App';
        $resolvableType = 'Factory';
        $classInfo = ClassInfo::from($this);

        $actual = $this->rule->buildClassCandidate($projectNamespace, $resolvableType, $classInfo);

        self::assertSame('\App\Rule\Factory', $actual);
    }

    public function test_build_trims_surrounding_backslashes_from_project_namespace(): void
    {
        // The namespace is trimmed so the generated candidate always has
        // exactly one backslash between segments, regardless of whether the
        // caller includes leading/trailing backslashes.
        $classInfo = ClassInfo::from($this);

        self::assertSame(
            '\App\Rule\Factory',
            $this->rule->buildClassCandidate('\\App\\', 'Factory', $classInfo),
        );
    }
}
