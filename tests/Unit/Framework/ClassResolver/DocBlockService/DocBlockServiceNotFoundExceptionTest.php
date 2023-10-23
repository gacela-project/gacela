<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceNotFoundException;
use GacelaTest\Unit\FakeModule\FakeFacade;
use PHPUnit\Framework\TestCase;

final class DocBlockServiceNotFoundExceptionTest extends TestCase
{
    public function test_exception_message(): void
    {
        $facade = new FakeFacade();

        $exception = new DocBlockServiceNotFoundException($facade, 'ResolvableType');

        $expected = <<<EOT
ClassResolver Exception
Cannot resolve the `ResolvableType` for your module `FakeModule`
You can fix this by adding the missing `ResolvableType` to your module.
E.g. `\GacelaTest\Unit\FakeModule\ResolvableType`

EOT;

        self::assertSame($expected, $exception->getMessage());
    }
}
