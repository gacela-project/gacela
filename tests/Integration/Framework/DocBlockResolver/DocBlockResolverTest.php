<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\DocBlockResolver\DocBlockResolvable;
use Gacela\Framework\DocBlockResolver\DocBlockResolver;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
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

    public function test_normalize_config(): void
    {
        $resolver = DocBlockResolver::fromCaller($this);
        $actual = $resolver->getDocBlockResolvable('getMethod');
        $expected = new DocBlockResolvable(GacelaConfig::class, 'Config');

        self::assertEquals($expected, $actual);
    }
}
