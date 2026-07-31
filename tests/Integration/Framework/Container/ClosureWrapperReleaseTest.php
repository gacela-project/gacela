<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Closure;
use Countable;
use Gacela\Framework\Container\Container;
use GacelaTest\Fixtures\StringValue;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function count;

/**
 * The decorator wraps every user closure so service closures receive the
 * decorator rather than the inner container, and marks each wrapper so `set()`
 * does not wrap it twice and drop its factory/protected mark.
 *
 * That mark used to live in an `SplObjectStorage`, which holds its keys
 * *strongly*. Nothing ever removed an entry, so a container retained every
 * closure ever handed to `set()`, `factory()`, `extend()` or `protect()` --
 * along with everything each one closed over -- for its whole lifetime, whether
 * or not the binding still existed. Overwriting one id 5000 times retained 5000
 * wrappers and 6.1mb to serve a single binding.
 *
 * These assert the mark is held *weakly*: it must survive exactly as long as
 * the closure it marks is reachable, and no longer.
 */
final class ClosureWrapperReleaseTest extends TestCase
{
    public function test_overwriting_a_binding_releases_the_previous_closure(): void
    {
        $container = new Container();

        for ($i = 0; $i < 50; ++$i) {
            $container->set('id', static fn (): StringValue => new StringValue('v'));
        }

        // One live binding, so at most one wrapper may still be marked.
        self::assertLessThanOrEqual(1, $this->markedCount($container));
        self::assertSame('v', $container->get('id')?->value());
    }

    public function test_removing_a_binding_releases_its_closure(): void
    {
        $container = new Container();

        for ($i = 0; $i < 50; ++$i) {
            $container->set("id{$i}", static fn (): StringValue => new StringValue('v'));
            $container->remove("id{$i}");
        }

        self::assertSame([], $container->getRegisteredServices());
        self::assertSame(0, $this->markedCount($container));
    }

    /**
     * Pins a limitation that is **not** ours to fix, rather than hiding it.
     *
     * `factory()` and `protect()` hand back a closure the caller may never
     * register, and the inner container marks both in an `SplObjectStorage` of
     * its own (`FactoryManager::$factoryInstances` / `$protectedInstances`),
     * which nothing ever cleans. So the wrapper stays reachable from upstream
     * whatever this class does, and our weak mark simply mirrors it.
     *
     * Filed as gacela-project/container#167. This fails loudly when that lands,
     * which is the point: the assertion is the reminder to drop it.
     */
    public function test_an_unregistered_factory_wrapper_is_still_retained_by_the_inner_container(): void
    {
        $container = new Container();

        for ($i = 0; $i < 50; ++$i) {
            $container->factory(static fn (): StringValue => new StringValue('v'));
        }

        self::assertSame(50, $this->markedCount($container));
    }

    /**
     * The path this class *does* own: a plain closure binding, overwritten, is
     * released -- no `factory()`/`protect()` mark upstream to hold it.
     */
    public function test_a_bind_closure_is_released_when_overwritten(): void
    {
        $container = new Container();

        for ($i = 0; $i < 50; ++$i) {
            $container->bind('id', static fn (): StringValue => new StringValue('v'));
        }

        self::assertLessThanOrEqual(1, $this->markedCount($container));
    }

    /**
     * The other half of the contract: while a binding is live, its wrapper stays
     * marked -- otherwise `set()` would wrap it a second time and silently drop
     * the factory mark, which is the bug the marking exists to prevent.
     */
    public function test_a_live_factory_binding_keeps_its_mark_and_its_semantics(): void
    {
        $container = new Container();
        $factory = $container->factory(static fn (): StringValue => new StringValue('v'));
        $container->set('id', $factory);

        self::assertSame(1, $this->markedCount($container));

        // A factory builds fresh per resolution; a dropped mark would have made
        // this one shared instance.
        self::assertNotSame($container->get('id'), $container->get('id'));
    }

    public function test_a_protected_closure_keeps_its_mark_while_registered(): void
    {
        $container = new Container();
        $protected = $container->protect(static fn (): string => 'never-invoked');
        $container->set('id', $protected);

        self::assertSame(1, $this->markedCount($container));
        self::assertInstanceOf(Closure::class, $container->get('id'));
    }

    private function markedCount(Container $container): int
    {
        /** @var Countable $marks */
        $marks = (new ReflectionProperty(Container::class, 'doNotWrap'))->getValue($container);

        return count($marks);
    }
}
