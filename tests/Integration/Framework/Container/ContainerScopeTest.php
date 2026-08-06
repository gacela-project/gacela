<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\LocatorInterface;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;

/**
 * `createScope()` was the decorator's one recorded non-forward for the whole of
 * 1.x: it returns a child container, and there was no way to hand callers a
 * *decorated* one -- forwarding the raw scope would have given its service
 * closures the inner container, so the documented provider signature
 * `static fn (Container $c) => $c->getLocator()` would fatal.
 *
 * Container 2.0's `withSelfReference()` is what closes that, so these pin the
 * two halves: a scope is decorated like its parent, and it behaves like a scope.
 *
 * Both halves are load-bearing now rather than latent: {@see \Gacela\Framework\AbstractFactory}
 * builds every module container as a scope of the app container, so a scope that
 * came back undecorated would break the documented provider signature on each
 * one of them.
 */
final class ContainerScopeTest extends TestCase
{
    public function test_a_scope_is_decorated_like_its_parent(): void
    {
        $scope = (new Container())->createScope();

        self::assertInstanceOf(Container::class, $scope);
        self::assertInstanceOf(LocatorInterface::class, $scope->getLocator());
    }

    /**
     * The reason the raw scope could not be forwarded: a provider closure has to
     * receive something that has `getLocator()`.
     */
    public function test_a_service_closure_in_a_scope_receives_the_decorator(): void
    {
        $scope = (new Container())->createScope();
        $scope->set('svc', static fn (Container $c): object => $c->getLocator());

        self::assertInstanceOf(LocatorInterface::class, $scope->get('svc'));
    }

    public function test_a_scope_resolves_what_its_parent_registered(): void
    {
        $parent = new Container();
        $parent->set('shared', new StringValue('from-parent'));

        self::assertSame('from-parent', $parent->createScope()->get('shared')?->value());
    }

    public function test_registering_on_a_scope_does_not_touch_the_parent(): void
    {
        $parent = new Container();
        $scope = $parent->createScope();

        $scope->set('local', new StringValue('scope-only'));

        self::assertSame('scope-only', $scope->get('local')?->value());
        self::assertFalse($parent->has('local'));
    }

    public function test_two_scopes_do_not_see_each_other(): void
    {
        $parent = new Container();
        $first = $parent->createScope();
        $second = $parent->createScope();

        // The property that makes a scope usable as a module or request
        // lifetime: an unnamespaced key in one cannot collide with another's.
        $first->set('key', new StringValue('first'));
        $second->set('key', new StringValue('second'));

        self::assertSame('first', $first->get('key')?->value());
        self::assertSame('second', $second->get('key')?->value());
    }

    public function test_a_scope_shadows_the_parent_without_mutating_it(): void
    {
        $parent = new Container();
        $parent->set('id', new StringValue('parent'));

        $scope = $parent->createScope();
        $scope->set('id', new StringValue('scope'));

        self::assertSame('scope', $scope->get('id')?->value());
        self::assertSame('parent', $parent->get('id')?->value());
    }
}
