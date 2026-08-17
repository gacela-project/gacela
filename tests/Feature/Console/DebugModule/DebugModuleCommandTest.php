<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModule;

use Closure;
use Gacela\Console\Infrastructure\Command\DebugModuleCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\CheckoutModuleConfig;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\CheckoutModuleFacade;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\CheckoutModuleFactory;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\CheckoutModuleProvider;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\PaymentGatewayInterface;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\PublishedOrder;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\StripeGateway;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\Transfer\OrderSummary;
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\TransferQueue\PendingOrders;
use GacelaTest\Feature\Console\DebugModule\Fixtures\GadgetModule\GadgetModuleFacade;
use GacelaTest\Feature\Console\DebugModule\Fixtures\ImperativeModule\ImperativeModuleProvider;
use GacelaTest\Feature\Console\DebugModule\Fixtures\WiredModule\WiredCollaborator;
use GacelaTest\Feature\Console\DebugModule\Fixtures\WiredModule\WiredModuleFacade;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function explode;
use function json_decode;
use function rtrim;
use function sprintf;

use function str_starts_with;

use const JSON_THROW_ON_ERROR;

final class DebugModuleCommandTest extends TestCase
{
    public function test_prints_all_four_resolved_classes_bindings_and_tree(): void
    {
        $tester = $this->debugModule(['module' => 'CheckoutModule'], $this->withStripeBinding());

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('Module: CheckoutModule', $display);

        // Each pillar reports the class that was resolved for it.
        $this->assertPillarReports($display, 'Facade', CheckoutModuleFacade::class);
        $this->assertPillarReports($display, 'Factory', CheckoutModuleFactory::class);
        $this->assertPillarReports($display, 'Config', CheckoutModuleConfig::class);
        $this->assertPillarReports($display, 'Provider', CheckoutModuleProvider::class);

        self::assertStringContainsString(
            sprintf('%s => %s', PaymentGatewayInterface::class, StripeGateway::class),
            $display,
        );
        self::assertStringContainsString('(no dependencies)', $display);
    }

    public function test_partial_module_marks_missing_types(): void
    {
        $tester = $this->debugModule(['module' => 'GadgetModule'], $this->withStripeBinding());

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();

        self::assertStringContainsString('Module: GadgetModule', $display);
        $this->assertPillarReports($display, 'Facade', GadgetModuleFacade::class);

        // A pillar the module does not define is called out rather than left blank.
        $this->assertPillarReports($display, 'Factory', '(not found)');
        $this->assertPillarReports($display, 'Config', '(not found)');
        $this->assertPillarReports($display, 'Provider', '(not found)');
    }

    public function test_reports_an_empty_container_as_having_no_bindings(): void
    {
        $tester = $this->debugModule(['module' => 'CheckoutModule']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertMatchesRegularExpression(
            '/Application bindings \(project-wide\):\s*\R\s*\(none\)/',
            $tester->getDisplay(),
        );
    }

    /**
     * The section reports the application's container, and the heading above it
     * names one module. That mismatch is the whole bug: an id another module's
     * Provider declares read as this module depending on it, and an id missing
     * from the list read as this module not binding it.
     *
     * Both halves are asserted, because the label is only worth having if the
     * thing it describes is really project-wide -- which is what makes the two
     * modules' sections identical.
     */
    public function test_the_bindings_section_says_it_is_the_applications_not_the_modules(): void
    {
        $checkout = $this->debugModule(['module' => 'CheckoutModule'], $this->withStripeBinding())->getDisplay();
        $gadget = $this->debugModule(['module' => 'GadgetModule'], $this->withStripeBinding())->getDisplay();

        self::assertStringContainsString('Application bindings (project-wide):', $checkout);
        self::assertStringNotContainsString('  Bindings:', $checkout);

        // GadgetModule binds nothing, and still reports Stripe -- because the
        // list was never its own.
        self::assertSame($this->bindingsSectionOf($checkout), $this->bindingsSectionOf($gadget));
        self::assertStringContainsString(StripeGateway::class, $this->bindingsSectionOf($gadget));
    }

    public function test_reports_contextual_bindings_next_to_the_plain_ones(): void
    {
        $tester = $this->debugModule(['module' => 'CheckoutModule'], static function (GacelaConfig $config): void {
            $config->when(CheckoutModuleFactory::class)
                ->needs(PaymentGatewayInterface::class)
                ->give(StripeGateway::class);
        });

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // A contextual binding names the class it applies to, so it cannot be
        // mistaken for a plain one.
        self::assertStringContainsString(
            sprintf(
                '%s (contextual for %s) => %s',
                PaymentGatewayInterface::class,
                CheckoutModuleFactory::class,
                StripeGateway::class,
            ),
            $tester->getDisplay(),
        );
    }

    public function test_lists_the_dependency_tree_of_a_wired_facade(): void
    {
        $tester = $this->debugModule(['module' => 'WiredModule']);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertPillarReports($display, 'Facade', WiredModuleFacade::class);

        // The facade's constructor dependency is drawn under the tree, labelled
        // with the parameter that pulled it in.
        self::assertMatchesRegularExpression(
            '/Dependency tree \(Facade\):\s*\R\s*└── ✓ \$\w+: ' . preg_quote(WiredCollaborator::class, '/') . '/u',
            $display,
        );
    }

    public function test_every_matching_module_is_rendered(): void
    {
        $tester = $this->debugModule(['module' => 'Module']);
        $lines = $this->linesOf($tester);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([
            'Module: CheckoutModule',
            'Module: GadgetModule',
            'Module: ImperativeModule',
            'Module: WiredModule',
        ], array_values(array_filter(
            $lines,
            static fn (string $line): bool => str_starts_with($line, 'Module: '),
        )));
    }

    public function test_unknown_module_prints_message_and_fails(): void
    {
        $tester = $this->debugModule(['module' => 'DoesNotExist']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No module matches "DoesNotExist".', $tester->getDisplay());
    }

    public function test_json_option_emits_the_whole_module_description(): void
    {
        $tester = $this->debugModule(
            ['module' => 'WiredModule', '--json' => true],
            $this->withStripeBinding(),
        );

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        self::assertStringStartsWith("[\n    {\n", $display);

        /** @var list<array<string, mixed>> $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([[
            'module' => 'WiredModule',
            'fullModuleName' => 'GacelaTest\\Feature\\Console\\DebugModule\\Fixtures\\WiredModule',
            'facade' => WiredModuleFacade::class,
            'factory' => null,
            'config' => null,
            'provider' => null,
            // WiredModule has no Provider at all, so there is nothing to declare.
            'provides' => [],
            // Nor does it publish anything: a Facade is the sanctioned crossing
            // rather than an export.
            'publicApi' => [],
            'bindings' => [PaymentGatewayInterface::class => StripeGateway::class],
            'contextualBindings' => [],
            'dependencyTree' => [WiredCollaborator::class],
        ]], $decoded);

        // JSON_UNESCAPED_SLASHES keeps the "bin/gacela"-style paths readable; the
        // namespace separators must still be escaped as JSON requires.
        self::assertStringContainsString('GacelaTest\\\\Feature', $display);
    }

    /**
     * The Provider is listed as one of the four pillars, and what it declares
     * is the question `getProvidedDependency('...')` asks. Nothing here
     * answered it: `Bindings` below reports the application's container, which
     * is the same list for every module printed.
     */
    public function test_lists_the_ids_the_modules_provider_declares(): void
    {
        $tester = $this->debugModule(['module' => 'CheckoutModule']);

        $display = $tester->getDisplay();
        self::assertStringContainsString('Provides (#[Provides]):', $display);
        self::assertStringContainsString(CheckoutModuleProvider::GATEWAY, $display);
        self::assertStringContainsString(CheckoutModuleProvider::RETRIES, $display);
    }

    /**
     * Sorted, so the section does not reorder itself between runs on a
     * Provider whose methods get shuffled around.
     */
    public function test_the_provided_ids_are_listed_in_a_stable_order(): void
    {
        $display = $this->debugModule(['module' => 'CheckoutModule'])->getDisplay();

        self::assertLessThan(
            strpos($display, CheckoutModuleProvider::RETRIES),
            strpos($display, CheckoutModuleProvider::GATEWAY),
        );
    }

    /**
     * A Provider that registers with `$container->set()` and declares no
     * attribute reports `(none)`, however many ids it registers. The heading
     * says `#[Provides]` for that reason, and finding the imperative ones means
     * running the Provider, which this command does not do.
     *
     * Pinned because the documented remedy for a misspelled id used to be
     * "check `debug:module`, which lists every id that module's Provider
     * declares" -- advice that sends a reader with an imperative Provider to a
     * command that shows them nothing.
     */
    public function test_a_provider_that_registers_imperatively_declares_nothing(): void
    {
        $display = $this->debugModule(['module' => 'ImperativeModule'])->getDisplay();

        self::assertStringContainsString(ImperativeModuleProvider::class, $display);
        self::assertStringNotContainsString(ImperativeModuleProvider::GATEWAY, $display);
        self::assertMatchesRegularExpression('/Provides \(#\[Provides\]\):\s*\R\s*\(none\)/u', $display);
    }

    public function test_a_module_without_a_provider_declares_nothing(): void
    {
        $display = $this->debugModule(['module' => 'WiredModule'])->getDisplay();

        self::assertStringContainsString('Provides (#[Provides]):', $display);
        self::assertMatchesRegularExpression('/Provides \(#\[Provides\]\):\s*\R\s*\(none\)/u', $display);
    }

    public function test_json_carries_the_provided_ids_too(): void
    {
        $display = $this->debugModule(['module' => 'CheckoutModule', '--json' => true])->getDisplay();

        /** @var list<array<string, mixed>> $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(
            [CheckoutModuleProvider::GATEWAY, CheckoutModuleProvider::RETRIES],
            $decoded[0]['provides'],
        );
    }

    /**
     * A module's surface is declared class by class -- an attribute here, a
     * `Transfer\` namespace there -- which is the right place to write it and
     * the wrong place to read it from. Answering "what does Checkout export"
     * used to mean opening every file in it.
     */
    public function test_lists_what_the_module_exports(): void
    {
        $display = $this->debugModule(['module' => 'CheckoutModule'])->getDisplay();

        self::assertStringContainsString('Public API (#[PublicApi] + namespace convention):', $display);
        self::assertStringContainsString(PublishedOrder::class, $display);
        self::assertStringContainsString(OrderSummary::class, $display);
    }

    /**
     * `TransferQueue` merely starts with the published segment `Transfer`. On a
     * prefix match the section would advertise a module's internals as exported.
     */
    public function test_a_namespace_that_merely_starts_with_a_published_segment_is_not_listed(): void
    {
        self::assertStringNotContainsString(
            PendingOrders::class,
            $this->debugModule(['module' => 'CheckoutModule'])->getDisplay(),
        );
    }

    /**
     * The four pillars are not an export: reaching a module through its Facade
     * is the sanctioned crossing, not something the module has to publish.
     */
    public function test_a_module_that_publishes_nothing_says_so(): void
    {
        $display = $this->debugModule(['module' => 'GadgetModule'])->getDisplay();

        self::assertMatchesRegularExpression(
            '/Public API \(#\[PublicApi\] \+ namespace convention\):\s*\R\s*\(none\)/u',
            $display,
        );
    }

    public function test_json_carries_the_public_api_too(): void
    {
        $display = $this->debugModule(['module' => 'CheckoutModule', '--json' => true])->getDisplay();

        /** @var list<array<string, mixed>> $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([PublishedOrder::class, OrderSummary::class], $decoded[0]['publicApi']);
    }

    public function test_tree_option_limits_output_to_the_dependency_tree(): void
    {
        $tester = $this->debugModule(
            ['module' => 'CheckoutModule', '--tree' => true],
            $this->withStripeBinding(),
        );

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Module: CheckoutModule', $display);
        self::assertStringContainsString('Dependency tree (Facade):', $display);

        // --tree drops the pillar and binding sections entirely.
        self::assertStringNotContainsString('Facade    →', $display);
        self::assertStringNotContainsString('Application bindings', $display);
    }

    /**
     * The `Application bindings` block, up to the next section.
     */
    private function bindingsSectionOf(string $display): string
    {
        $parts = explode('Application bindings (project-wide):', $display);
        self::assertArrayHasKey(1, $parts, 'no bindings section: comparing two empty strings would pass for nothing');

        return explode('Dependency tree', $parts[1])[0];
    }

    /**
     * @return Closure(GacelaConfig):void
     */
    private function withStripeBinding(): Closure
    {
        return static function (GacelaConfig $config): void {
            $config->addBinding(PaymentGatewayInterface::class, StripeGateway::class);
        };
    }

    /**
     * @param array<string, bool|string> $input
     * @param null|Closure(GacelaConfig):void $configFn
     */
    private function debugModule(array $input, ?Closure $configFn = null): CommandTester
    {
        Gacela::bootstrap(__DIR__ . '/Fixtures', static function (GacelaConfig $config) use ($configFn): void {
            $config->resetInMemoryCache();
            if ($configFn instanceof Closure) {
                $configFn($config);
            }
        });

        $tester = new CommandTester(new DebugModuleCommand());
        $tester->execute($input);

        return $tester;
    }

    /**
     * Asserts the $pillar row reports $class, without pinning the arrow glyph or
     * the column padding that separates them.
     */
    private function assertPillarReports(string $display, string $pillar, string $class): void
    {
        self::assertMatchesRegularExpression(
            '/' . $pillar . '\b[^\r\n]*' . preg_quote($class, '/') . '/',
            $display,
            sprintf('Expected the %s pillar to report "%s"', $pillar, $class),
        );
    }

    /**
     * @return list<string>
     */
    private function linesOf(CommandTester $tester): array
    {
        return array_map(
            rtrim(...),
            explode("\n", $tester->getDisplay()),
        );
    }
}
