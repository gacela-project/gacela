<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\Attribute\Cacheable;
use Gacela\StaticAnalysis\Rules\CacheableKeyIgnoresArgumentsAnalyser;
use Gacela\StaticAnalysis\Rules\CacheableWithoutCachedCallAnalyser;
use GacelaTest\Unit\StaticAnalysis\Double\FakeAnalysedClass;
use GacelaTest\Unit\StaticAnalysis\Double\ParseSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CacheableAttributeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function names(): iterable
    {
        yield 'foreign import' => ['use Hyperf\Cache\Annotation\Cacheable;', 'Cacheable', 0];
        yield 'foreign fully qualified' => ['', '\Other\Cacheable', 0];
        yield 'foreign local namespace' => ['', 'Cacheable', 0];
        yield 'Gacela import' => ['use Gacela\Framework\Attribute\Cacheable;', 'Cacheable', 1];
        yield 'Gacela mixed-case namespace' => ['', '\\' . str_replace('Attribute', 'ATTRIBUTE', Cacheable::class), 1];
        yield 'Gacela fully qualified' => ['', '\\' . Cacheable::class, 1];
        yield 'Gacela namespace import' => ['use Gacela\Framework\Attribute;', 'Attribute\Cacheable', 1];
        yield 'Gacela renamed import' => ['use Gacela\Framework\Attribute\Cacheable as Remember;', 'Remember', 1];
    }

    #[DataProvider('names')]
    public function test_both_rules_use_the_resolved_attribute(string $imports, string $name, int $expected): void
    {
        $source = '<?php namespace App\Example; ' . $imports . ' final class ExampleFacade {'
            . '#[' . $name . '(60, "users")] public function user(int $id): int { return $id; }}';
        $class = new FakeAnalysedClass('App\Example\ExampleFacade', [AbstractFacade::class]);
        foreach ([ParseSource::classInAsPhpStanResolves($source), ParseSource::classInWithNameAttributes($source)] as $node) {
            self::assertCount($expected, (new CacheableWithoutCachedCallAnalyser())->analyse($node, $class));
            self::assertCount($expected, (new CacheableKeyIgnoresArgumentsAnalyser())->analyse($node->getMethods()[0], $class));
        }
    }
}
