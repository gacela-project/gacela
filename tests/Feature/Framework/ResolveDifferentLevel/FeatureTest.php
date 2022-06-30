<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentLevel;

use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ResolveDifferentLevel\vendor\Persona\ModuleA\Facade as VendorPersonaFacade;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_facade_aware(): void
    {
        $facade = new VendorPersonaFacade();

        self::assertSame('Override string from ModuleA', $facade->sayHi());
    }
}
