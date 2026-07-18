<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceNotFoundException;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\ClassResolver\DocBlockService\MissingClassDefinitionException;
use Gacela\Framework\Gacela;
use Gacela\Framework\ServiceResolver\DocBlockResolvable;
use Gacela\Framework\ServiceResolver\DocBlockResolver;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeCommandWithUnrelatedImport;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeConfig;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeFacade;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeFactory;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeRandomService;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class DocBlockResolverTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    /**
     * Verifies that attempting to resolve a non-existent class from a @method annotation
     * throws MissingClassDefinitionException.
     */
    public function test_throws_exception_when_class_definition_is_missing(): void
    {
        $this->expectException(MissingClassDefinitionException::class);

        (new FakeCommand())->getUnknown();
    }

    /**
     * An undocumented method on a class that has a docblock for a different
     * method plus unrelated `use` imports must throw, not silently resolve to
     * the first imported class.
     */
    public function test_throws_exception_for_undocumented_method_with_unrelated_import(): void
    {
        $this->expectException(MissingClassDefinitionException::class);

        (new FakeCommandWithUnrelatedImport())->getSomethingUndocumented();
    }

    /**
     * Verifies that DocBlockServiceResolver throws an exception when given an empty
     * service name, as there's no valid service to resolve.
     */
    public function test_throws_exception_when_service_name_is_empty(): void
    {
        $this->expectException(DocBlockServiceNotFoundException::class);

        $resolver = new DocBlockServiceResolver('');
        $command = new FakeCommand();
        $resolver->resolve($command);
    }

    /**
     * Tests that DocBlockResolver correctly resolves service types from @method annotations.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('serviceResolutionProvider')]
    public function test_resolves_service_type_from_docblock(
        object $caller,
        string $methodName,
        string $expectedClass,
        string $expectedSuffix,
    ): void {
        $resolver = DocBlockResolver::fromCaller($caller);
        $actual = $resolver->getDocBlockResolvable($methodName);
        $expected = new DocBlockResolvable($expectedClass, $expectedSuffix);

        self::assertEquals(
            $expected,
            $actual,
            sprintf(
                'Failed to resolve %s() from %s to %s',
                $methodName,
                $caller::class,
                $expectedClass,
            ),
        );
    }

    /**
     * fromClassName() must build a resolver that resolves identically to fromCaller();
     * its earlier absence made cache:warm attribute pre-warming a silent no-op.
     */
    public function test_from_class_name_resolves_equivalently_to_from_caller(): void
    {
        $fromCaller = DocBlockResolver::fromCaller(new FakeFacade())
            ->getDocBlockResolvable('getFactory');
        $fromClassName = DocBlockResolver::fromClassName(FakeFacade::class)
            ->getDocBlockResolvable('getFactory');

        self::assertEquals(new DocBlockResolvable(FakeFactory::class, 'Factory'), $fromClassName);
        self::assertEquals($fromCaller, $fromClassName);
    }

    /**
     * Provides test cases for service resolution from different callers.
     *
     * @return iterable<string, array{object, string, class-string, string}>
     */
    public static function serviceResolutionProvider(): iterable
    {
        yield 'Facade resolution from Command' => [
            new FakeCommand(),
            'getFacade',
            FakeFacade::class,
            'Facade',
        ];

        yield 'Factory resolution from Facade' => [
            new FakeFacade(),
            'getFactory',
            FakeFactory::class,
            'Factory',
        ];

        yield 'Config resolution from Factory' => [
            new FakeFactory(),
            'getConfig',
            FakeConfig::class,
            'Config',
        ];

        yield 'Custom service resolution from Command' => [
            new FakeCommand(),
            'getRandom',
            FakeRandomService::class,
            'FakeRandomService',
        ];
    }
}
