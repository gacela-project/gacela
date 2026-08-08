<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules;

use Gacela\Framework\AbstractFactory;
use Gacela\StaticAnalysis\Rules\FactoryDoesNotCallFacadeAnalyser;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\TestCase;

use function array_map;
use function sprintf;

final class FactoryDoesNotCallFacadeAnalyserTest extends TestCase
{
    public function test_a_factory_wiring_its_own_module_is_allowed(): void
    {
        self::assertSame([], $this->analyse('return new CheckoutService();'));
    }

    public function test_instantiating_a_facade_is_reported(): void
    {
        $violations = $this->analyse('return new App\Billing\BillingFacade();');

        self::assertCount(1, $violations);
        self::assertSame(
            'Factory App\Checkout\CheckoutFactory must not instantiate a Facade (found: new App\Billing\BillingFacade). Depend on other modules through their Facade via the Provider.',
            $violations[0]->message,
        );
        self::assertSame('gacela.factoryInstantiatesFacade', $violations[0]->identifier);
    }

    public function test_calling_get_facade_is_reported(): void
    {
        $violations = $this->analyse('return $this->getFacade()->doThing();');

        self::assertCount(1, $violations);
        self::assertSame(
            'Factory App\Checkout\CheckoutFactory must not call $this->getFacade(); same-module access goes through the Factory itself, cross-module access goes through the Provider.',
            $violations[0]->message,
        );
        self::assertSame('gacela.factoryCallsGetFacade', $violations[0]->identifier);
    }

    /**
     * They are different mistakes with different corrections, so a factory
     * making both hears about both.
     */
    public function test_both_kinds_are_reported_from_one_class(): void
    {
        $violations = $this->analyse("new App\Billing\BillingFacade();\n\$this->getFacade();");

        self::assertSame(
            ['gacela.factoryInstantiatesFacade', 'gacela.factoryCallsGetFacade'],
            array_map(static fn (Violation $v): string => $v->identifier, $violations),
        );
    }

    /**
     * Two call sites are two places to go and change, so both are reported
     * rather than collapsed into one finding per factory.
     */
    public function test_every_call_site_is_reported(): void
    {
        self::assertCount(2, $this->analyse("\$this->getFacade();\n\$this->getFacade();"));
    }

    public function test_every_instantiation_is_reported(): void
    {
        self::assertCount(2, $this->analyse(
            "new App\Billing\BillingFacade();\nnew App\Shipping\ShippingFacade();",
        ));
    }

    /**
     * A namespace segment ending in `Facade` is not a Facade class.
     */
    public function test_only_the_last_segment_decides_what_is_a_facade(): void
    {
        self::assertSame([], $this->analyse('return new App\BillingFacade\Service();'));
    }

    /**
     * `new $class` names nothing this rule could match on. Reporting it would
     * mean guessing, and staying silent is the honest answer.
     */
    public function test_a_dynamic_instantiation_is_not_reported(): void
    {
        self::assertSame([], $this->analyse('return new $facadeClass();'));
    }

    public function test_get_facade_on_another_object_is_not_reported(): void
    {
        self::assertSame([], $this->analyse('return $other->getFacade();'));
    }

    public function test_a_dynamic_method_name_is_not_reported(): void
    {
        self::assertSame([], $this->analyse('return $this->$method();'));
    }

    public function test_a_class_that_is_not_a_factory_is_not_checked(): void
    {
        $node = ParseSource::classIn($this->sourceWith('return new App\Billing\BillingFacade();'));
        $analyser = new FactoryDoesNotCallFacadeAnalyser();

        self::assertSame([], $analyser->analyse($node, new FakeAnalysedClass('App\Checkout\CheckoutService')));
    }

    /**
     * @return list<Violation>
     */
    private function analyse(string $body): array
    {
        $node = ParseSource::classIn($this->sourceWith($body));
        $analyser = new FactoryDoesNotCallFacadeAnalyser();
        $class = new FakeAnalysedClass('App\Checkout\CheckoutFactory', [AbstractFactory::class]);

        return $analyser->analyse($node, $class);
    }

    private function sourceWith(string $body): string
    {
        return sprintf("<?php\nfinal class CheckoutFactory\n{\n    public function create()\n    {\n%s\n    }\n}", $body);
    }
}
