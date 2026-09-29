<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\RegistrationPrecedence;

use ArrayObject;
use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    private const APP_ID = ArrayObject::class;

    /**
     * @return iterable<string, array{Closure(GacelaConfig):void, Closure(GacelaConfig):void, string}>
     */
    public static function providerClosureAgainstFile(): iterable
    {
        yield 'addBinding in both: the file wins' => [
            self::binding('closure'),
            self::binding('file'),
            'file',
        ];
        yield 'addLazy in both: the file wins' => [
            self::lazy('closure'),
            self::lazy('file'),
            'file',
        ];
        yield 'addLazy in the closure beats addBinding in the file' => [
            self::lazy('closure'),
            self::binding('file'),
            'closure',
        ];
        yield 'addLazy in the file beats addBinding in the closure' => [
            self::binding('closure'),
            self::lazy('file'),
            'file',
        ];
        yield 'addLazy beats addBinding in the same file' => [
            static function (): void {},
            static function (GacelaConfig $config): void {
                self::lazy('lazy')($config);
                self::binding('binding')($config);
            },
            'lazy',
        ];
    }

    /**
     * @param Closure(GacelaConfig):void $inClosure
     * @param Closure(GacelaConfig):void $inFile
     */
    #[DataProvider('providerClosureAgainstFile')]
    public function test_which_of_the_closure_and_gacela_php_wins(Closure $inClosure, Closure $inFile, string $expected): void
    {
        $this->bootstrap(static function (GacelaConfig $config) use ($inClosure): void {
            $inClosure($config);
        }, $inFile);

        self::assertEquals(new ArrayObject([$expected]), Gacela::container()->get(self::APP_ID));
    }

    public function test_an_app_level_registration_loses_to_the_module_provider(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->addLazy(Module\Provider::SERVICE, static fn (): ArrayObject => new ArrayObject(['lazy']));
        });

        self::assertEquals(new ArrayObject(['provider']), (new Module\Facade())->getService());
    }

    public function test_extend_service_reaches_the_module_provider(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->extendService(Module\Provider::SERVICE, self::appendExtended(...));
        });

        self::assertEquals(new ArrayObject(['provider', 'extended']), (new Module\Facade())->getService());
    }

    public function test_add_lazy_and_extend_service_on_one_id_extends_only_the_app_level_service(): void
    {
        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->addLazy(Module\Provider::SERVICE, static fn (): ArrayObject => new ArrayObject(['lazy']));
            $config->extendService(Module\Provider::SERVICE, self::appendExtended(...));
        });

        self::assertEquals(new ArrayObject(['lazy', 'extended']), Gacela::container()->get(Module\Provider::SERVICE));
        self::assertEquals(new ArrayObject(['provider']), (new Module\Facade())->getService());
    }

    public static function appendExtended(ArrayObject $service): void
    {
        $service->append('extended');
    }

    /**
     * @return Closure(GacelaConfig):void
     */
    private static function binding(string $value): Closure
    {
        return static function (GacelaConfig $config) use ($value): void {
            $config->addBinding(self::APP_ID, new ArrayObject([$value]));
        };
    }

    /**
     * @return Closure(GacelaConfig):void
     */
    private static function lazy(string $value): Closure
    {
        return static function (GacelaConfig $config) use ($value): void {
            $config->addLazy(self::APP_ID, static fn (): ArrayObject => new ArrayObject([$value]));
        };
    }

    /**
     * @param Closure(GacelaConfig):void $inClosure
     * @param null|Closure(GacelaConfig):void $inFile
     */
    private function bootstrap(Closure $inClosure, ?Closure $inFile = null): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($inClosure, $inFile): void {
            $config->resetInMemoryCache();
            $config->addExternalService('registerInFile', $inFile ?? static function (): void {});
            $inClosure($config);
        });
    }
}
