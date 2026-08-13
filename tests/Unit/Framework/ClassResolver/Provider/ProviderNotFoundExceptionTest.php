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

        $expectedStart = <<<'EOT'
ClassResolver Exception
Cannot resolve the `Provider` for your module `FakeModule`
You can fix this by adding the missing `Provider` to your module.
E.g. `\GacelaTest\Unit\FakeModule\FakeModuleProvider`
EOT;

        self::assertStringStartsWith($expectedStart, $exception->getMessage());
    }

    /**
     * The tips used to be a fixed `facade_not_found` text, so this message named
     * `Provider` four times and then advised on a `Facade` -- and this exception
     * is never raised for a Facade, which is constructed rather than resolved.
     */
    public function test_the_tips_advise_on_the_kind_the_message_names(): void
    {
        $message = (new ProviderNotFoundException(new FakeFacade()))->getMessage();

        self::assertStringContainsString('Ensure your Provider extends AbstractProvider', $message);
        self::assertStringContainsString('Verify the Provider file name matches the class name', $message);
        self::assertStringNotContainsString('Facade', $message);
    }
}
