<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\ClassResolver;

use Gacela\Console\Infrastructure\Command\DebugEventsCommand;
use Gacela\Console\Infrastructure\Command\DoctorCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\Checkout\CheckoutFacade;
use GacelaTest\Feature\Framework\ListenerAttribute\Shop\Log;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function dirname;

use const DIRECTORY_SEPARATOR;

final class FileDisabledListenersTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__ . DIRECTORY_SEPARATOR . 'FileDisabled', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->setProjectNamespaces(['GacelaTest\\Feature\\Framework\\ListenerAttribute\\Shop']);
            $config->setAppModulePaths([dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'ListenerAttribute' . DIRECTORY_SEPARATOR . 'Shop']);
            $config->registerGenericListener(static function (): void {
                Log::$lines[] = 'generic';
            });
        });
        Log::$lines = [];
    }

    protected function tearDown(): void
    {
        Log::$lines = [];
        Gacela::resetCache();
    }

    public function test_file_disables_closure_and_attribute_listeners(): void
    {
        Config::getInstance()->get('missing', 'default');
        (new CheckoutFacade())->placeOrder(7);

        self::assertSame([], Log::$lines);
        self::assertFalse(Config::getInstance()->getSetupGacela()->areEventListenersEnabled());
    }

    public function test_debug_events_reports_the_registered_listeners_as_disabled(): void
    {
        $tester = new CommandTester(new DebugEventsCommand());
        $tester->execute([]);

        self::assertStringContainsString('disableEventListeners() is in effect', $tester->getDisplay());
        self::assertStringContainsString('via registerGenericListener()', $tester->getDisplay());
    }

    public function test_doctor_reports_the_registered_listeners_as_disabled(): void
    {
        $tester = new CommandTester(new DoctorCommand());
        $tester->execute([]);

        self::assertStringContainsString('event listeners are disabled', $tester->getDisplay());
    }
}
