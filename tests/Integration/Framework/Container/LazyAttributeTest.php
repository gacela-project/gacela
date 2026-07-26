<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Container\Attribute\Lazy;
use Gacela\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * `#[Lazy]` (container 1.1) joins `#[Inject]`, `#[Singleton]` and `#[Factory]`
 * as an attribute the container honours, so it reaches Gacela through
 * `AbstractFactory::make()` and container resolution without any framework
 * change.
 *
 * What is asserted is deliberately modest. On PHP 8.4 the container returns a
 * lazy ghost whose constructor has not run; on 8.3 it constructs eagerly, and
 * the upstream docs say that difference is "unobservable apart from the
 * timing". Gacela's floor is 8.3 and CI covers 8.3, 8.4 and 8.5, so an
 * assertion about *when* the constructor ran would pass on one row of the
 * matrix and fail on another.
 *
 * So this pins the part that holds everywhere: the container accepts the
 * attribute and hands back a working instance of the real class. That is enough
 * to catch the attribute being rejected or the resolver mishandling it.
 */
final class LazyAttributeTest extends TestCase
{
    public function test_a_lazy_class_resolves_to_a_working_instance_of_itself(): void
    {
        $service = (new Container())->make(LazyAttributeTestService::class);

        self::assertInstanceOf(LazyAttributeTestService::class, $service);
        self::assertSame('worked', $service->work());
    }

    public function test_a_lazy_class_resolves_its_constructor_dependencies(): void
    {
        $service = (new Container())->make(LazyAttributeTestConsumer::class);

        self::assertSame('worked', $service->delegate());
    }
}

#[Lazy]
final class LazyAttributeTestService
{
    public function work(): string
    {
        return 'worked';
    }
}

#[Lazy]
final class LazyAttributeTestConsumer
{
    public function __construct(
        private readonly LazyAttributeTestService $service,
    ) {
    }

    public function delegate(): string
    {
        return $this->service->work();
    }
}
