<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModule;

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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class DebugModuleCommandTest extends TestCase
{
    private CommandTester $command;

    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__ . '/Fixtures', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(PaymentGatewayInterface::class, StripeGateway::class);
        });

        $this->command = new CommandTester(new DebugModuleCommand());
    }

    public function test_prints_all_four_resolved_classes_bindings_and_tree(): void
    {
        $exitCode = $this->command->execute(['module' => 'CheckoutModule']);
        $output = $this->command->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('CheckoutModule', $output);
        self::assertStringContainsString(CheckoutModuleFacade::class, $output);
        self::assertStringContainsString(CheckoutModuleFactory::class, $output);
        self::assertStringContainsString(CheckoutModuleConfig::class, $output);
        self::assertStringContainsString(CheckoutModuleProvider::class, $output);
        self::assertStringContainsString(PaymentGatewayInterface::class, $output);
        self::assertStringContainsString(StripeGateway::class, $output);
        self::assertStringContainsString('Dependency tree', $output);
    }

    public function test_partial_module_marks_missing_types(): void
    {
        $exitCode = $this->command->execute(['module' => 'GadgetModule']);
        $output = $this->command->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(GadgetModuleFacade::class, $output);
        self::assertStringContainsString('(not found)', $output);
    }

    public function test_unknown_module_prints_message_and_fails(): void
    {
        $exitCode = $this->command->execute(['module' => 'DoesNotExist']);
        $output = $this->command->getDisplay();

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('No module matches "DoesNotExist"', $output);
    }

    public function test_json_option_emits_parseable_json(): void
    {
        $exitCode = $this->command->execute(['module' => 'CheckoutModule', '--json' => true]);
        $output = $this->command->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);

        /** @var list<array<string,mixed>> $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(1, $decoded);
        self::assertSame('CheckoutModule', $decoded[0]['module']);
        self::assertSame(CheckoutModuleFacade::class, $decoded[0]['facade']);
        self::assertSame(CheckoutModuleFactory::class, $decoded[0]['factory']);
        self::assertArrayHasKey('bindings', $decoded[0]);
        self::assertArrayHasKey('dependencyTree', $decoded[0]);
    }

    public function test_tree_option_limits_output_to_dependency_tree(): void
    {
        $exitCode = $this->command->execute(['module' => 'CheckoutModule', '--tree' => true]);
        $output = $this->command->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Dependency tree', $output);
        self::assertStringNotContainsString('Bindings', $output);
    }
}
