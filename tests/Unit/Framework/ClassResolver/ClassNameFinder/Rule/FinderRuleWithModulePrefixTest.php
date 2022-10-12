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
}
