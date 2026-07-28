<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Container\Container as GacelaContainer;
use Gacela\Framework\Container\Container;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Container-API behaviour that only the decorator's forwarding can get wrong.
 *
 * These methods used to be inherited, so there was no Gacela code to test. With
 * `Gacela\Container\Container` final from 1.0, every one of them is a
 * hand-written delegation -- which introduces a bug class that did not exist
 * before: forwarding to the *neighbouring* method. `bindIf` calling `bind`, or
 * `singletonIf` calling `singleton`, differ only in whether an existing binding
 * is overwritten, and nothing else in the suite would notice.
 *
 * So these assert the semantics that distinguish each pair, not that a call was
 * made.
 */
final class ContainerDelegationTest extends TestCase
{
    public function test_bind_if_does_not_overwrite_an_existing_binding(): void
    {
        $container = new Container();
        $container->bind(StringValueInterface::class, static fn (): StringValue => new StringValue('first'));

        $container->bindIf(StringValueInterface::class, static fn (): StringValue => new StringValue('second'));

        self::assertSame('first', $container->get(StringValueInterface::class)?->value());
    }

    public function test_bind_overwrites_where_bind_if_would_not(): void
    {
        $container = new Container();
        $container->bind(StringValueInterface::class, static fn (): StringValue => new StringValue('first'));

        $container->bind(StringValueInterface::class, static fn (): StringValue => new StringValue('second'));

        self::assertSame('second', $container->get(StringValueInterface::class)?->value());
    }

    /**
     * The "does not overwrite" tests above cannot tell a `bindIf` that declined
     * to overwrite from one that was never called at all. This pins the other
     * half of the contract: on an unbound abstract it must actually register.
     */
    public function test_bind_if_registers_when_nothing_is_bound_yet(): void
    {
        $container = new Container();

        $container->bindIf(StringValueInterface::class, static fn (): StringValue => new StringValue('only'));

        self::assertSame('only', $container->get(StringValueInterface::class)?->value());
    }

    public function test_singleton_if_registers_when_nothing_is_bound_yet(): void
    {
        $container = new Container();

        $container->singletonIf(StringValueInterface::class, static fn (): StringValue => new StringValue('only'));

        self::assertSame('only', $container->get(StringValueInterface::class)?->value());
    }

    public function test_singleton_if_does_not_overwrite_an_existing_binding(): void
    {
        $container = new Container();
        $container->singleton(StringValueInterface::class, static fn (): StringValue => new StringValue('first'));

        $container->singletonIf(StringValueInterface::class, static fn (): StringValue => new StringValue('second'));

        self::assertSame('first', $container->get(StringValueInterface::class)?->value());
    }

    public function test_a_singleton_returns_the_same_instance_every_time(): void
    {
        $container = new Container();
        $container->singleton(StringValueInterface::class, static fn (): StringValue => new StringValue('x'));

        self::assertSame(
            $container->get(StringValueInterface::class),
            $container->get(StringValueInterface::class),
        );
    }

    public function test_after_resolving_runs_once_the_service_is_resolved(): void
    {
        $container = new Container();
        $container->set('service', static fn (): string => 'value');

        $seen = null;
        $container->afterResolving('service', static function (mixed $instance) use (&$seen): void {
            $seen = $instance;
        });

        self::assertNull($seen, 'the callback must not run before the service is resolved');

        $container->get('service');

        self::assertSame('value', $seen);
    }

    public function test_tagged_resolves_every_service_registered_under_the_tag(): void
    {
        $container = new Container();
        $container->set('a', static fn (): string => 'A');
        $container->set('b', static fn (): string => 'B');

        $container->tag(['a', 'b'], 'letters');

        self::assertSame(['A', 'B'], [...$container->tagged('letters')]);
    }

    /**
     * A keyed tag is a lookup table: asking for one key must build that entry
     * and nothing else. Forwarding `taggedByKey()` to `tagged()` and indexing
     * the result would pass an equality assertion while building the whole tag,
     * so the other entries are made to fail if they are ever resolved.
     */
    public function test_tagged_by_key_resolves_only_the_entry_asked_for(): void
    {
        $container = new Container();
        $container->set('email', static fn (): string => 'EMAIL');
        $container->set('sms', static function (): string {
            self::fail('an unrequested keyed entry must not be resolved');
        });

        $container->tag(['email' => 'email', 'sms' => 'sms'], 'handlers');

        self::assertSame('EMAIL', $container->taggedByKey('handlers', 'email'));
    }

    public function test_tagged_keys_lists_only_the_keyed_entries(): void
    {
        $container = new Container();
        $container->set('a', static fn (): string => 'A');
        $container->set('b', static fn (): string => 'B');

        $container->tag(['first' => 'a'], 'mixed');
        $container->tag(['b'], 'mixed');

        self::assertSame(['first'], $container->taggedKeys('mixed'));
        self::assertSame(['first' => 'A', 0 => 'B'], [...$container->tagged('mixed')]);
    }

    /**
     * `has()` answers "would get() resolve this", which is true of anything
     * instantiable. `provides()` answers the narrower question the debug
     * commands actually need: does the container own something for this id.
     * Forwarding one to the other would go unnoticed everywhere but here.
     */
    public function test_provides_is_narrower_than_has(): void
    {
        $container = new Container();

        self::assertTrue($container->has(stdClass::class), 'an instantiable class satisfies has()');
        self::assertFalse($container->provides(stdClass::class), '...but the container owns nothing for it');

        $container->set(stdClass::class, static fn (): stdClass => new stdClass());

        self::assertTrue($container->provides(stdClass::class));
    }

    public function test_provides_reports_a_registered_binding(): void
    {
        $container = new Container();
        $container->bind(StringValueInterface::class, static fn (): StringValue => new StringValue('x'));

        self::assertTrue($container->provides(StringValueInterface::class));
    }

    public function test_provides_is_false_for_an_interface_with_no_binding(): void
    {
        $container = new Container();

        self::assertFalse($container->provides(StringValueInterface::class));
    }

    public function test_warm_up_leaves_the_class_resolvable(): void
    {
        $container = new Container();

        $container->warmUp([stdClass::class]);

        self::assertInstanceOf(stdClass::class, $container->make(stdClass::class));
    }

    public function test_write_compiled_cache_produces_plans_that_load_back(): void
    {
        $file = sys_get_temp_dir() . '/gacela-compiled-plans-' . uniqid('', true) . '.php';
        $container = new Container();

        try {
            $container->writeCompiledCache([stdClass::class], $file);

            self::assertTrue(is_file($file), 'writeCompiledCache() must write the plans file');
            self::assertArrayHasKey(stdClass::class, GacelaContainer::loadCompiledCache($file));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
