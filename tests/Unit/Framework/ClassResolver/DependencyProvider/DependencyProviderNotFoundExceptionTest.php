<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\DependencyProvider;

use Gacela\Framework\ClassResolver\DependencyProvider\DependencyProviderNotFoundException;
use PHPUnit\Framework\TestCase;

final class DependencyProviderNotFoundExceptionTest extends TestCase
{
    public function test_exception_message(): void
    {
        $exception = new DependencyProviderNotFoundException($this);

        $expected = <<<EOT
ClassResolver Exception
Cannot resolve the `DependencyProvider` for your module `DependencyProvider`
You can fix this by adding the missing `DependencyProvider` to your module.
E.g. `\GacelaTest\Unit\Framework\ClassResolver\DependencyProvider`

EOT;

        self::assertSame($expected, $exception->getMessage());
    }
}
