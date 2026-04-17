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

        $this->analyse(
            [__DIR__ . '/Fixture/FacadeDelegate/BadFacade.php'],
            [
                [$prefix . 'multipleStatements' . $suffix, 12],
                [$prefix . 'localLogic' . $suffix, 19],
                [$prefix . 'controlFlow' . $suffix, 24],
                [$prefix . 'notAllowedRoot' . $suffix, 33],
                [$prefix . 'cachedNonDelegation' . $suffix, 38],
                [$prefix . 'cachedMultiStmt' . $suffix, 43],
                [$prefix . 'somethingElse' . $suffix, 52],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new FacadeOnlyDelegatesRule();
    }
}
