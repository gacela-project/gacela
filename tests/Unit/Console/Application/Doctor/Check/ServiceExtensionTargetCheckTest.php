<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\Check\ServiceExtensionTargetCheck;
use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Domain\AllAppModules\AppModule;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\BindOnlyProvider;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\SetProvider;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\StubFacade;
use GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures\ThrowingProvider;
use PHPUnit\Framework\TestCase;

final class ServiceExtensionTargetCheckTest extends TestCase
{
    public function test_no_extensions_is_ok(): void
    {
        $result = (new ServiceExtensionTargetCheck([], [], []))->run();

        self::assertSame(CheckStatus::Ok, $result->status);
        self::assertContains('no service extensions registered', $result->details);
    }

    public function test_an_id_a_provider_sets_is_matched(): void
    {
        $check = new ServiceExtensionTargetCheck(
            [$this->module(SetProvider::class)],
            [SetProvider::ID],
            [],
        );

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function test_a_mistyped_id_warns_naming_the_id(): void
    {
        $check = new ServiceExtensionTargetCheck(
            [$this->module(SetProvider::class)],
            ['knwon.id'],
            [],
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString('knwon.id', $result->details[0]);
    }

    public function test_an_id_the_app_container_provides_is_matched(): void
    {
        $check = new ServiceExtensionTargetCheck([], ['app.id'], ['app.id']);

        self::assertSame(CheckStatus::Ok, $check->run()->status);
    }

    /**
     * The queue only drains through `set()`. A singleton() registration is
     * real, but an extension on its id still silently never applies -- the
     * check must warn, which it does by reading `getRegisteredServices()`
     * (the drain's own store) rather than the broader `provides()`.
     */
    public function test_an_id_registered_only_through_singleton_warns(): void
    {
        $check = new ServiceExtensionTargetCheck(
            [$this->module(BindOnlyProvider::class)],
            [BindOnlyProvider::SINGLETON_ID],
            [],
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString(BindOnlyProvider::SINGLETON_ID, $result->details[0]);
    }

    public function test_a_module_without_a_provider_is_skipped(): void
    {
        $check = new ServiceExtensionTargetCheck(
            [$this->module(null)],
            ['some.id'],
            [],
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        // Exactly the unmatched id: a skipped module must not add a
        // provider-failure line of its own.
        self::assertCount(1, $result->details);
        self::assertStringContainsString('some.id', $result->details[0]);
    }

    public function test_a_provider_slot_holding_a_non_provider_class_is_skipped(): void
    {
        $check = new ServiceExtensionTargetCheck(
            [$this->module(StubFacade::class)],
            ['some.id'],
            [],
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        // Exactly the unmatched id: the class is skipped, not run and crashed.
        self::assertCount(1, $result->details);
        self::assertStringContainsString('some.id', $result->details[0]);
    }

    /**
     * A Provider that cannot run outside its deployment must not crash the
     * diagnosis of every other one.
     */
    public function test_a_throwing_provider_is_reported_and_the_rest_still_diagnosed(): void
    {
        $check = new ServiceExtensionTargetCheck(
            [$this->module(ThrowingProvider::class), $this->module(SetProvider::class)],
            [SetProvider::ID],
            [],
        );

        $result = $check->run();

        self::assertSame(CheckStatus::Warn, $result->status);
        self::assertStringContainsString(ThrowingProvider::class, $result->details[0]);
        self::assertStringContainsString('this provider needs a database', $result->details[0]);
    }

    /**
     * @param class-string|null $providerClass
     */
    private function module(?string $providerClass): AppModule
    {
        return new AppModule(
            'App\TestModule',
            'TestModule',
            StubFacade::class,
            null,
            null,
            $providerClass,
        );
    }
}
