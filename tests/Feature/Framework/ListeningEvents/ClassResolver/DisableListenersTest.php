<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ListeningEvents\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ListeningEvents\ClassResolver\Module\Facade;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DisableListenersTest extends TestCase
{
    #[PreserveGlobalState(false)]
    public function test_disable_class_resolver_listener(): void
    {
        Gacela::bootstrap(__DIR__, function (GacelaConfig $config): void {
            $config->disableEventListeners();

            $config->registerGenericListener(function (): never {
                $this->throwExceptionListener();
            });
        });

        $facade = new Facade();
        $facade->doString();

        $facade = new Facade();
        $facade->doString();

        $this->expectNotToPerformAssertions();
    }

    public function throwExceptionListener(): never
    {
        throw new RuntimeException('This should never be called');
    }
}
