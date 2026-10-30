<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Container;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Exception\AliasLoopException;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

final class AliasServicesTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new ReflectionClass(Gacela::class);
        $method = $reflection->getMethod('resetCache');
        $method->invoke(null);
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(Gacela::class);
        $method = $reflection->getMethod('resetCache');
        $method->invoke(null);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function registrations(): iterable
    {
        yield 'binding' => ['binding'];
        yield 'factory' => ['factory'];
        yield 'lazy' => ['lazy'];
    }

    /**
     * @param 'binding'|'factory'|'lazy' $registration
     */
    #[DataProvider('registrations')]
    public function test_alias_chains_preserve_the_target_lifetime(string $registration): void
    {
        $calls = 0;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($registration, &$calls): void {
            $config->addAlias('db', 'database');
            $config->addAlias('database', 'original-service');

            $factory = static function () use (&$calls): stdClass {
                ++$calls;

                return new stdClass();
            };
            match ($registration) {
                'binding' => $config->addBinding('original-service', $factory),
                'factory' => $config->addFactory('original-service', $factory),
                'lazy' => $config->addLazy('original-service', $factory),
            };
        });
        self::assertSame(0, $calls);
        $first = Gacela::container()->get('db');
        $second = Gacela::container()->get('db');

        self::assertInstanceOf(stdClass::class, $first);
        self::assertInstanceOf(stdClass::class, $second);
        self::assertNotSame($first, $second);
        self::assertSame(2, $calls);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function loops(): iterable
    {
        yield 'self' => [['a' => 'a'], 'a -> a'];
        yield 'two aliases' => [['a' => 'b', 'b' => 'a'], 'a -> b -> a'];
        yield 'chain into loop' => [['entry' => 'a', 'a' => 'b', 'b' => 'a'], 'entry -> a -> b -> a'];
    }

    /**
     * @param array<string, string> $aliases
     */
    #[DataProvider('loops')]
    public function test_alias_loops_fail_during_bootstrap(array $aliases, string $loop): void
    {
        $this->expectException(AliasLoopException::class);
        $this->expectExceptionMessage($loop);

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($aliases): void {
            foreach ($aliases as $alias => $target) {
                $config->addAlias($alias, $target);
            }
        });
    }

    public function test_alias_resolves_to_original_service(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addBinding('original-service', static fn (): stdClass => new stdClass());
            $config->addAlias('alias-service', 'original-service');
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $alias = $container->get('alias-service');

        self::assertInstanceOf(stdClass::class, $alias, 'Alias should resolve using the binding');
    }

    public function test_multiple_aliases_for_same_factory(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addFactory('factory', static fn (): stdClass => new stdClass());
            $config->addAlias('alias1', 'factory');
            $config->addAlias('alias2', 'factory');
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $instance1 = $container->get('alias1');
        $instance2 = $container->get('alias2');

        self::assertInstanceOf(stdClass::class, $instance1);
        self::assertInstanceOf(stdClass::class, $instance2);
        self::assertNotSame($instance1, $instance2, 'Aliases to factory should each return new instances');
    }

    public function test_alias_with_factory_service(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addFactory('factory-service', static fn (): stdClass => new stdClass());
            $config->addAlias('alias-factory', 'factory-service');
        });

        $container = Container::withConfig(\Gacela\Framework\Config\Config::getInstance());

        $instance1 = $container->get('factory-service');
        $instance2 = $container->get('alias-factory');

        self::assertInstanceOf(stdClass::class, $instance1);
        self::assertInstanceOf(stdClass::class, $instance2);
        self::assertNotSame($instance1, $instance2, 'Factory service should return new instances even through alias');
    }
}
