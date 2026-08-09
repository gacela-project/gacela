<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\PHPStan\Rules\FacadeOnlyDelegatesRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<FacadeOnlyDelegatesRule>
 */
final class FacadeOnlyDelegatesRuleTest extends RuleTestCase
{
    public function test_allows_all_delegation_patterns(): void
    {
        $this->analyse([__DIR__ . '/Fixture/FacadeDelegate/GoodFacade.php'], []);
    }

    public function test_skips_abstract_methods(): void
    {
        $this->analyse([__DIR__ . '/Fixture/FacadeDelegate/AbstractMethodFacade.php'], []);
    }

    public function test_skips_non_facade_classes(): void
    {
        $this->analyse([__DIR__ . '/Fixture/FacadeDelegate/NotAFacade.php'], []);
    }

    public function test_reports_all_bad_patterns(): void
    {
        $prefix = 'Facade method ' . \GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeDelegate\BadFacade::class . '::';
        $suffix = '() must only delegate to $this->getFactory()/getConfig()/getProvider(); no inline logic allowed.';

        $tip = 'Move the logic into the Factory and have this method call it.';

        $this->analyse(
            [__DIR__ . '/Fixture/FacadeDelegate/BadFacade.php'],
            [
                [$prefix . 'multipleStatements' . $suffix, 12, $tip],
                [$prefix . 'localLogic' . $suffix, 19, $tip],
                [$prefix . 'controlFlow' . $suffix, 24, $tip],
                [$prefix . 'notAllowedRoot' . $suffix, 33, $tip],
                [$prefix . 'cachedNonDelegation' . $suffix, 38, $tip],
                [$prefix . 'cachedMultiStmt' . $suffix, 43, $tip],
                [$prefix . 'somethingElse' . $suffix, 52, $tip],
                [$prefix . 'singleIfStatement' . $suffix, 57, $tip],
                [$prefix . 'delegatesOnLocalVariable' . $suffix, 69, $tip],
                [$prefix . 'dynamicMethodName' . $suffix, 74, $tip],
                [$prefix . 'notCachedWrapper' . $suffix, 79, $tip],
                [$prefix . 'cachedWithoutArgs' . $suffix, 84, $tip],
                [$prefix . 'cachedWithNonClosure' . $suffix, 89, $tip],
                [$prefix . 'cachedOnNullsafeThis' . $suffix, 94, $tip],
                [$prefix . 'cachedClosureWithLeadingDelegation' . $suffix, 99, $tip],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new FacadeOnlyDelegatesRule();
    }
}
