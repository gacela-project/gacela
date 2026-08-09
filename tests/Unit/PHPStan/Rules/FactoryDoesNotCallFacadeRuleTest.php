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
    private const NEW_TIP = 'Declare that Facade in your Provider and read it with getProvidedDependency().';

    private const CALL_TIP = 'Same-module wiring belongs in this Factory; another module belongs in the Provider.';

    public function test_reports_new_facade_instantiation(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixture/FactoryCallsFacade/BadFactoryNewFacade.php'],
            [
                [
                    'Factory GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\BadFactoryNewFacade must not instantiate a Facade (found: new GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\ShopFacade). Depend on other modules through their Facade via the Provider.',
                    9,
                    self::NEW_TIP,
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
                    self::CALL_TIP,
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
                    self::NEW_TIP,
                ],
                [
                    'Factory GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\MultiViolationFactory must not call $this->getFacade(); same-module access goes through the Factory itself, cross-module access goes through the Provider.',
                    9,
                    self::CALL_TIP,
                ],
            ],
        );
    }

    public function test_skips_every_non_violating_reference_and_reports_all_violations(): void
    {
        $factory = 'Factory GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\MixedOrderBadFactory';
        $newSuffix = '. Depend on other modules through their Facade via the Provider.';

        $this->analyse(
            [__DIR__ . '/Fixture/FactoryCallsFacade/MixedOrderBadFactory.php'],
            [
                [$factory . ' must not instantiate a Facade (found: new GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\ShopFacade)' . $newSuffix, 10, self::NEW_TIP],
                [$factory . ' must not instantiate a Facade (found: new Facade)' . $newSuffix, 10, self::NEW_TIP],
                [$factory . ' must not instantiate a Facade (found: new GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade\Sub\Facade)' . $newSuffix, 10, self::NEW_TIP],
                [$factory . ' must not call $this->getFacade(); same-module access goes through the Factory itself, cross-module access goes through the Provider.', 10, self::CALL_TIP],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new FactoryDoesNotCallFacadeRule();
    }
}
