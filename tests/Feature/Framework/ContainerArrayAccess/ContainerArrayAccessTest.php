<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerArrayAccess;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;

final class ContainerArrayAccessTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addBinding(StringValueInterface::class, StringValue::class);
        });
    }

    public function test_reading_a_binding_via_array_access_resolves_it(): void
    {
        $container = Gacela::container();

        self::assertInstanceOf(StringValue::class, $container[StringValueInterface::class]);
    }

    public function test_array_access_set_isset_unset_round_trip(): void
    {
        $container = Gacela::container();

        // isset() reflects explicitly registered instances, not lazy bindings.
        self::assertFalse(isset($container['my-service']));

        $container['my-service'] = new StringValue('via-array-access');

        self::assertTrue(isset($container['my-service']));
        /** @var StringValue $service */
        $service = $container['my-service'];
        self::assertSame('via-array-access', $service->value());

        unset($container['my-service']);
        self::assertFalse(isset($container['my-service']));
    }
}
