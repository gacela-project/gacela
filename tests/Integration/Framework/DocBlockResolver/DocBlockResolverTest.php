<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\DocBlockResolver\DocBlockResolvable;
use Gacela\Framework\DocBlockResolver\DocBlockResolver;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\DocBlockResolver\Module\FakeCommand;
use GacelaTest\Integration\Framework\DocBlockResolver\Module\FakeConfig;
use GacelaTest\Integration\Framework\DocBlockResolver\Module\FakeFacade;
use GacelaTest\Integration\Framework\DocBlockResolver\Module\FakeFactory;
use PHPUnit\Framework\TestCase;

final class DocBlockResolverTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerGenericListener(static function (GacelaEventInterface $event): void {
                # dump('Triggered -> ' . \get_class($event)); # useful for debugging
            });
        });
    }

    public function test_normalize_facade(): void
    {
        $resolver = DocBlockResolver::fromCaller(new FakeCommand());
        $actual = $resolver->getDocBlockResolvable('getFacade');
        $expected = new DocBlockResolvable(FakeFacade::class, 'Facade');

        self::assertEquals($expected, $actual);
    }

    public function test_normalize_factory(): void
    {
        $resolver = DocBlockResolver::fromCaller(new FakeFacade());
        $actual = $resolver->getDocBlockResolvable('getFactory');
        $expected = new DocBlockResolvable(FakeFactory::class, 'Factory');

        self::assertEquals($expected, $actual);
    }

    public function test_normalize_config(): void
    {
        $resolver = DocBlockResolver::fromCaller(new FakeFactory());
        $actual = $resolver->getDocBlockResolvable('getConfig');
        $expected = new DocBlockResolvable(FakeConfig::class, 'Config');

        self::assertEquals($expected, $actual);
    }
}
