<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\DependencyProvider;

use Gacela\Framework\ClassResolver\DependencyProvider\DependencyProviderNotFoundException;
use GacelaTest\Unit\FakeModule\FakeFacade;
use PHPUnit\Framework\TestCase;

final class DependencyProviderNotFoundExceptionTest extends TestCase
{
    public function test_exception_message(): void
    {
        $facade = new FakeFacade();

        $exception = new DependencyProviderNotFoundException($facade);

        $expected = <<<EOT
ClassResolver Exception
Cannot resolve the `DependencyProvider` for your module `FakeModule`
You can fix this by adding the missing `DependencyProvider` to your module.
E.g. `\GacelaTest\Unit\FakeModule\DependencyProvider`

EOT;

        self::assertSame($expected, $exception->getMessage());
    }
}
