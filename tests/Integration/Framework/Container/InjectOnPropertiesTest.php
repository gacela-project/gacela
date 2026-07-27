<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Container\Attribute\Inject;
use Gacela\Container\Exception\DependencyInvalidArgumentException;
use Gacela\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * `#[Inject]` on properties (container 1.2) reaches Gacela through
 * `AbstractFactory::make()` and container resolution without any framework
 * change, the same way `#[Lazy]` did.
 *
 * It exists for classes whose constructor is not yours to change -- a base
 * class from a vendor package, or one whose signature is fixed by a framework
 * contract. Constructor injection stays the default everywhere else, because a
 * dependency in the signature is visible to a reader and to a plain `new`.
 *
 * What is pinned here is the part Gacela's users would notice if it regressed:
 * that the attribute is honoured at all, that it reaches private and inherited
 * properties, and that the misuse cases fail with the container's own
 * exception rather than a raw PHP error surfacing from inside Gacela.
 */
final class InjectOnPropertiesTest extends TestCase
{
    public function test_a_property_marked_with_inject_is_resolved(): void
    {
        $consumer = (new Container())->make(InjectPropertyConsumer::class);

        self::assertSame('worked', $consumer->work());
    }

    public function test_an_inherited_private_property_is_resolved_too(): void
    {
        $consumer = (new Container())->make(InjectPropertyChild::class);

        self::assertSame('worked', $consumer->work());
    }

    /**
     * A readonly property cannot be written after construction, so injecting
     * one is a mistake the container names rather than letting PHP throw from
     * somewhere inside the resolver.
     */
    public function test_a_readonly_property_is_rejected_by_name(): void
    {
        $this->expectException(DependencyInvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/InjectReadonlyConsumer::\$service.+is readonly and cannot be injected/s',
        );

        (new Container())->make(InjectReadonlyConsumer::class);
    }
}

final class InjectPropertyService
{
    public function work(): string
    {
        return 'worked';
    }
}

class InjectPropertyParent
{
    #[Inject]
    private InjectPropertyService $service;

    public function work(): string
    {
        return $this->service->work();
    }
}

final class InjectPropertyChild extends InjectPropertyParent
{
}

final class InjectPropertyConsumer
{
    #[Inject]
    private InjectPropertyService $service;

    public function work(): string
    {
        return $this->service->work();
    }
}

final class InjectReadonlyConsumer
{
    #[Inject]
    public readonly InjectPropertyService $service;

    // Initialized, so the class is well-formed and the only thing wrong with
    // it is the attribute -- which is what the test is about.
    public function __construct()
    {
        $this->service = new InjectPropertyService();
    }
}
