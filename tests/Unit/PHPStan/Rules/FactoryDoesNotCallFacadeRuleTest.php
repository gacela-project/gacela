<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\PHPStan\Rules\FactoryDoesNotCallFacadeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<FactoryDoesNotCallFacadeRule>
 */
final class FactoryDoesNotCallFacadeRuleTest extends RuleTestCase
{
    public function test_reports_new_facade_instantiation(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/FactoryCallsFacade/BadFactoryNewFacade.php'],
            [
                [
                    'Factory GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\BadFactoryNewFacade must not instantiate a Facade (found: new GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\ShopFacade). Depend on other modules through their Facade via the Provider.',
                    9,
                ],
            ],
        );
    }

    public function test_reports_get_facade_call_on_this(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/FactoryCallsFacade/BadFactoryGetFacade.php'],
            [
                [
                    'Factory GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\BadFactoryGetFacade must not call $this->getFacade(); same-module access goes through the Factory itself, cross-module access goes through the Provider.',
                    9,
                ],
            ],
        );
    }

    public function test_ignores_clean_factory(): void
    {
        $this->analyse([__DIR__ . '/Fixture/FactoryCallsFacade/CleanFactory.php'], []);
    }

    public function test_skips_non_factory_classes(): void
    {
        $this->analyse([__DIR__ . '/Fixture/FactoryCallsFacade/NonFactoryCallsGetFacade.php'], []);
    }

    public function test_detects_multiple_violations(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/FactoryCallsFacade/MultiViolationFactory.php'],
            [
                [
                    'Factory GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\MultiViolationFactory must not instantiate a Facade (found: new GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\ShopFacade). Depend on other modules through their Facade via the Provider.',
                    9,
                ],
                [
                    'Factory GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\MultiViolationFactory must not call $this->getFacade(); same-module access goes through the Factory itself, cross-module access goes through the Provider.',
                    9,
                ],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new FactoryDoesNotCallFacadeRule();
    }
}
