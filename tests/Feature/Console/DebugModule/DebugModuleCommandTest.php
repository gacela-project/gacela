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
use GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\StripeGateway;
use GacelaTest\Feature\Console\DebugModule\Fixtures\GadgetModule\GadgetModuleFacade;
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
        self::assertMatchesRegularExpression('/Bindings:\s*\R\s*\(none\)/', $tester->getDisplay());
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
            'bindings' => [PaymentGatewayInterface::class => StripeGateway::class],
            'contextualBindings' => [],
            'dependencyTree' => [WiredCollaborator::class],
        ]], $decoded);

        // JSON_UNESCAPED_SLASHES keeps the "bin/gacela"-style paths readable; the
        // namespace separators must still be escaped as JSON requires.
        self::assertStringContainsString('GacelaTest\\\\Feature', $display);
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
        self::assertStringNotContainsString('Bindings:', $display);
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
