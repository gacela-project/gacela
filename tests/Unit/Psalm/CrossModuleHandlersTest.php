<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Psalm\CrossModuleCallRules;
use Gacela\Psalm\CrossModuleRules;
use Gacela\Psalm\CrossModuleSettings;
use Gacela\StaticAnalysis\Violation;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;
use Psalm\Config;
use Psalm\NodeTypeProvider;
use Psalm\Type\Atomic\TNamedObject;
use Psalm\Type\Union;
use ReflectionClass;
use ReflectionProperty;
use SimpleXMLElement;

use function array_map;

/**
 * Both halves of the opt-in boundary check, driven directly.
 *
 * `CrossModuleRulesTest` proves the same through a real `vendor/bin/psalm`, but
 * that is a subprocess and invisible to coverage. Reporting stays out of reach:
 * it goes through Psalm's `IssueBuffer`, which wants a live `ProjectAnalyzer`.
 */
final class CrossModuleHandlersTest extends TestCase
{
    private const ROOT = 'App\Modules';

    private ?Config $previousConfig = null;

    protected function setUp(): void
    {
        // Building any psalm type reads the global config for its string-length
        // limit, and a unit test has no analysis to have initialised one.
        $instance = new ReflectionProperty(Config::class, 'instance');
        $this->previousConfig = $instance->getValue();
        $instance->setValue(null, (new ReflectionClass(Config::class))->newInstanceWithoutConstructor());
    }

    /**
     * The handlers keep their analyser in a static, because Psalm calls them
     * statically. Clearing it keeps these tests independent of their order.
     */
    protected function tearDown(): void
    {
        CrossModuleRules::configure(null);
        CrossModuleCallRules::configure(null);
        (new ReflectionProperty(Config::class, 'instance'))->setValue(null, $this->previousConfig);
    }

    public function test_the_written_half_finds_nothing_until_it_is_configured(): void
    {
        self::assertFalse(CrossModuleRules::isConfigured());
        self::assertSame([], $this->writtenViolations());
    }

    public function test_the_written_half_reports_a_crossing_once_configured(): void
    {
        $this->configure();

        self::assertSame(['gacela.crossModuleWithoutFacade'], $this->identifiers($this->writtenViolations()));
    }

    public function test_the_written_half_allows_a_facade(): void
    {
        $this->configure();

        self::assertSame([], $this->writtenViolations('new App\Modules\Billing\BillingFacade();'));
    }

    public function test_the_call_half_finds_nothing_until_it_is_configured(): void
    {
        self::assertFalse(CrossModuleCallRules::isConfigured());
        self::assertSame([], $this->callViolations('App\Modules\Billing\Domain\InvoiceRepository'));
    }

    public function test_the_call_half_reports_a_crossing_once_configured(): void
    {
        $this->configure();

        self::assertSame(
            ['gacela.crossModuleMethodCall'],
            $this->identifiers($this->callViolations('App\Modules\Billing\Domain\InvoiceRepository')),
        );
    }

    /**
     * The receiver's classes come off the inferred type, so a union receiver is
     * several crossings to answer for.
     */
    public function test_the_call_half_reads_every_class_a_union_receiver_can_be(): void
    {
        $this->configure();

        $violations = $this->callViolations(
            'App\Modules\Billing\Domain\InvoiceRepository',
            'App\Modules\Shipping\Domain\Labels',
        );

        self::assertCount(2, $violations);
    }

    /**
     * A receiver Psalm could not type is not evidence of a violation, and
     * guessing there would turn the rule into noise.
     */
    public function test_the_call_half_says_nothing_about_an_unresolved_receiver(): void
    {
        $this->configure();

        self::assertSame([], $this->callViolations());
    }

    public function test_the_call_half_allows_a_facade_receiver(): void
    {
        $this->configure();

        self::assertSame([], $this->callViolations('App\Modules\Billing\BillingFacade'));
    }

    private function configure(): void
    {
        $settings = CrossModuleSettings::fromPluginConfig(new SimpleXMLElement(
            '<pluginClass><crossModule rootNamespace="' . self::ROOT . '"/></pluginClass>',
        ));

        self::assertNotNull($settings);

        CrossModuleRules::configure($settings);
        CrossModuleCallRules::configure($settings);
    }

    /**
     * @return list<Violation>
     */
    private function writtenViolations(string $body = 'new App\Modules\Billing\Domain\InvoiceRepository();'): array
    {
        $source = "<?php\nfinal class CheckoutFactory\n{\n    public function create()\n    {\n" . $body . "\n    }\n}";

        return CrossModuleRules::violationsIn(
            ParseSource::classIn($source),
            new FakeAnalysedClass('App\Modules\Checkout\CheckoutFactory'),
        );
    }

    /**
     * @return list<Violation>
     */
    private function callViolations(string ...$receiverClasses): array
    {
        $call = (new NodeFinder())->findFirstInstanceOf(
            [ParseSource::classIn('<?php final class C { public function f() { return $this->dep->run(); } }')],
            MethodCall::class,
        );
        self::assertInstanceOf(MethodCall::class, $call);

        $types = $this->createStub(NodeTypeProvider::class);
        $types->method('getType')->willReturn(
            $receiverClasses === []
                ? null
                : new Union(array_map(static fn (string $c): TNamedObject => new TNamedObject($c), $receiverClasses)),
        );

        return CrossModuleCallRules::violationsFor($call, 'App\Modules\Checkout\CheckoutFactory', $types);
    }

    /**
     * @param list<Violation> $violations
     *
     * @return list<string>
     */
    private function identifiers(array $violations): array
    {
        return array_map(static fn (Violation $v): string => $v->identifier, $violations);
    }
}
