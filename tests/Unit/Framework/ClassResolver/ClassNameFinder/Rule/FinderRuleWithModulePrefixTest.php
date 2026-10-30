<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\ClassNameFinder\Rule;

use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleWithModulePrefix;
use PHPUnit\Framework\TestCase;

final class FinderRuleWithModulePrefixTest extends TestCase
{
    private FinderRuleWithModulePrefix $rule;

    protected function setUp(): void
    {
        $this->rule = new FinderRuleWithModulePrefix();
    }

    public function test_a_global_caller_has_no_synthetic_module_namespace(): void
    {
        self::assertSame(
            '\RootFactory',
            $this->rule->buildClassCandidate('', 'Factory', ClassInfo::from('RootFacade', 'Factory')),
        );
        self::assertSame('\Root\RootFactory', $this->rule->buildClassCandidate('', 'Factory', ClassInfo::from('Root\\RootFacade', 'Factory')));
    }

    public function test_build_without_project_namespace(): void
    {
        $projectNamespace = '';
        $resolvableType = 'Factory';
        $classInfo = ClassInfo::from($this);

        $actual = $this->rule->buildClassCandidate($projectNamespace, $resolvableType, $classInfo);

        self::assertSame('\Rule\RuleFactory', $actual);
    }

    public function test_build_with_project_namespace(): void
    {
        $projectNamespace = 'App';
        $resolvableType = 'Factory';
        $classInfo = ClassInfo::from($this);

        $actual = $this->rule->buildClassCandidate($projectNamespace, $resolvableType, $classInfo);

        self::assertSame('\App\Rule\RuleFactory', $actual);
    }

    public function test_build_trims_surrounding_backslashes_from_project_namespace(): void
    {
        $classInfo = ClassInfo::from($this);

        self::assertSame(
            '\App\Rule\RuleFactory',
            $this->rule->buildClassCandidate('\\App\\', 'Factory', $classInfo),
        );
    }
}
