<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceAware;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Command\HelloCommand;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_custom_service(): void
    {
        $this->expectOutputString('Hello, fake-name(id:123), and Goodbye');

        $facade = new HelloCommand();
        $facade->echoHello(123);
    }
}
