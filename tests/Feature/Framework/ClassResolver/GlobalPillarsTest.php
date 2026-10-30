<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\MissingPillarMethodException;
use Gacela\Framework\Gacela;
use OrphanFacade;
use OtherRootFacade;
use PHPUnit\Framework\TestCase;
use RootFacade;
use RootFactory;

final class GlobalPillarsTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (['RootFactory', 'RootFacade', 'OtherRootFactory', 'OtherRootFacade', 'OrphanFacade'] as $class) {
            require_once __DIR__ . '/GlobalPillars/' . $class . '.php';
        }

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache()->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_global_facades_resolve_their_factories_without_sharing_entries(): void
    {
        self::assertSame('root', (new RootFacade())->hello());
        self::assertSame('other', (new OtherRootFacade())->hello());
        self::assertSame('root', (new RootFacade())->hello());
    }

    public function test_global_factory_overrides_use_the_resolver_key(): void
    {
        $resolver = new FactoryResolver();
        self::assertSame('root', $resolver->resolve(RootFacade::class)->createHello());
        $replacement = new RootFactory('override');

        Gacela::overrideExistingResolvedClass('\\RootFactory', $replacement);

        self::assertSame($replacement, $resolver->resolve(RootFacade::class));
    }

    public function test_a_missing_global_factory_names_the_caller_and_namespace_problem(): void
    {
        $factory = (new FactoryResolver())->resolve(OrphanFacade::class);
        $this->expectException(MissingPillarMethodException::class);
        $this->expectExceptionMessage('`OrphanFacade` (which has no namespace) has no `Factory`');

        $factory->createHello();
    }
}
