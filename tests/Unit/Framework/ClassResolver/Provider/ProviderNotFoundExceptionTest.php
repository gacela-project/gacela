<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Provider;

use Gacela\Framework\ClassResolver\Provider\ProviderNotFoundException;
use GacelaTest\Unit\FakeModule\FakeFacade;
use PHPUnit\Framework\TestCase;

final class ProviderNotFoundExceptionTest extends TestCase
{
    public function test_exception_message(): void
    {
        $facade = new FakeFacade();

        $exception = new ProviderNotFoundException($facade);

        $expected = <<<EOT
ClassResolver Exception
Cannot resolve the `Provider` for your module `FakeModule`
You can fix this by adding the missing `Provider` to your module.
E.g. `\GacelaTest\Unit\FakeModule\Provider`

EOT;

        self::assertSame($expected, $exception->getMessage());
    }
}
