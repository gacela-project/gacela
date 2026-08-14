<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugProvides;

use Gacela\Console\Infrastructure\Command\DebugProvidesCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugProvides\Fixtures\BillingModule\BillingModuleProvider;
use GacelaTest\Feature\Console\DebugProvides\Fixtures\ShippingModule\ShippingModuleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * The inverse lookup nothing answered: `getProvidedDependency()` returns `null`
 * for an id nothing declares and says nothing about it, so the next question is
 * "who was supposed to declare this?" -- and the only way to ask it was
 * `debug:module`, once per module.
 */
final class DebugProvidesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_it_names_the_provider_and_method_behind_an_id(): void
    {
        $display = $this->debugProvides([]);

        self::assertStringContainsString(BillingModuleProvider::GATEWAY, $display);
        self::assertStringContainsString('BillingModuleProvider', $display);
        self::assertStringContainsString('gateway()', $display);
    }

    public function test_it_reaches_every_module_not_just_the_first(): void
    {
        $display = $this->debugProvides([]);

        self::assertStringContainsString(BillingModuleProvider::GATEWAY, $display);
        self::assertStringContainsString(ShippingModuleProvider::CARRIER, $display);
    }

    public function test_the_argument_narrows_to_the_ids_containing_it(): void
    {
        $display = $this->debugProvides(['id' => 'CARRIER']);

        self::assertStringContainsString(ShippingModuleProvider::CARRIER, $display);
        self::assertStringNotContainsString(BillingModuleProvider::GATEWAY, $display);
    }

    /**
     * The run somebody chasing a null actually makes. An empty table would not
     * answer them; the point of the command is to say that nothing declares it.
     */
    public function test_an_id_nothing_declares_says_so(): void
    {
        $display = $this->debugProvides(['id' => 'NO_SUCH_ID']);

        self::assertStringContainsString('No #[Provides] id contains "NO_SUCH_ID"', $display);
    }

    public function test_json_carries_the_same_answer(): void
    {
        /** @var array<string, list<array{module: string, provider: string, method: string}>> $decoded */
        $decoded = json_decode($this->debugProvides(['--json' => true]), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey(BillingModuleProvider::GATEWAY, $decoded);
        self::assertSame(
            BillingModuleProvider::class,
            $decoded[BillingModuleProvider::GATEWAY][0]['provider'],
        );
    }

    /**
     * Two modules declaring one id is not a collision -- each resolves through
     * its own container -- so both rows are kept rather than one shadowing the
     * other, which is the whole reason to look here.
     */
    public function test_one_id_declared_in_two_modules_keeps_both_rows(): void
    {
        /** @var array<string, list<array{module: string, provider: string, method: string}>> $decoded */
        $decoded = json_decode($this->debugProvides(['--json' => true]), true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(2, $decoded[BillingModuleProvider::SHARED]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function debugProvides(array $input): string
    {
        $tester = new CommandTester(new DebugProvidesCommand());
        $tester->execute($input);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        return $tester->getDisplay();
    }
}
