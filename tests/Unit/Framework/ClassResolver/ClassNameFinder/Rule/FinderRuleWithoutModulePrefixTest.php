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
}
