<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceNotFoundException;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\ClassResolver\DocBlockService\MissingClassDefinitionException;
use Gacela\Framework\ClassResolver\Facade\FacadeResolver;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\Gacela;
use Gacela\Framework\ServiceResolver\DocBlockResolvable;
use Gacela\Framework\ServiceResolver\DocBlockResolver;
use GacelaTest\Integration\Framework\ServiceResolver\Billing\BillingAdminConfig;
use GacelaTest\Integration\Framework\ServiceResolver\Billing\BillingAdminFacade;
use GacelaTest\Integration\Framework\ServiceResolver\Billing\BillingAdminFactory;
use GacelaTest\Integration\Framework\ServiceResolver\Billing\BillingFacade;
use GacelaTest\Integration\Framework\ServiceResolver\Billing\Facade as ShortBillingFacade;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeAdminCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeCommandWithUnrelatedImport;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeConfig;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeConfigurationLoader;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeFacade;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeFactory;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeLoaderCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeNoDocBlockCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeProseCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeRandomService;
use GacelaTest\Integration\Framework\ServiceResolver\Reports\ReportsExportFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

use function sprintf;

final class DocBlockResolverTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    #[DataProvider('namedPillars')]
    public function test_a_service_map_returns_the_exact_pillar_it_names(string $method, string $expectedClass): void
    {
        $command = new FakeAdminCommand();

        $resolved = $command->{$method}();

        self::assertInstanceOf($expectedClass, $resolved);
        self::assertSame($resolved, $command->{$method}());
        self::assertSame($resolved, (new FakeAdminCommand())->{$method}());
    }

    public function test_a_conventional_mapping_shares_the_module_resolver_instance(): void
    {
        $facade = (new FacadeResolver())->resolve(BillingFacade::class);

        self::assertSame($facade, (new FakeAdminCommand())->getFacade());
    }

    #[DataProvider('conventionalFacadeOrders')]
    public function test_both_conventional_spellings_keep_their_exact_instances(bool $shortFirst): void
    {
        $command = new FakeAdminCommand();
        if ($shortFirst) {
            self::assertInstanceOf(ShortBillingFacade::class, $command->getShortFacade());
            self::assertInstanceOf(BillingFacade::class, $command->getFacade());
        } else {
            self::assertInstanceOf(BillingFacade::class, $command->getFacade());
            self::assertInstanceOf(ShortBillingFacade::class, $command->getShortFacade());
        }

        self::assertSame($command->getFacade(), (new FacadeResolver())->resolve(BillingFacade::class));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function conventionalFacadeOrders(): iterable
    {
        yield 'short first' => [true];
        yield 'prefixed first' => [false];
    }

    #[DataProvider('mappedFacadeMethods')]
    public function test_mapped_pillars_dispatch_created_then_cached_events(string $method): void
    {
        $events = [];
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$events): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->registerSpecificListener(ResolvedClassCreatedEvent::class, static function () use (&$events): void {
                $events[] = 'created';
            });
            $config->registerSpecificListener(ResolvedClassCachedEvent::class, static function () use (&$events): void {
                $events[] = 'cached';
            });
        });
        $events = [];
        (new FakeAdminCommand())->{$method}();
        (new FakeAdminCommand())->{$method}();

        self::assertSame(['created', 'cached'], $events);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mappedFacadeMethods(): iterable
    {
        yield 'conventional' => ['getFacade'];
        yield 'named' => ['getAdmin'];
    }

    public function test_a_named_pillar_uses_the_configured_container_factory(): void
    {
        $expected = new BillingAdminFacade();
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($expected): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
            $config->addFactory(BillingAdminFacade::class, static fn (): BillingAdminFacade => $expected);
        });

        self::assertSame($expected, (new FakeAdminCommand())->getAdmin());
    }

    public function test_a_cache_reset_discards_the_named_pillar_instance(): void
    {
        $original = (new FakeAdminCommand())->getAdmin();

        $this->setUp();

        self::assertNotSame($original, (new FakeAdminCommand())->getAdmin());
    }

    public function test_an_override_takes_precedence_over_a_cached_named_pillar(): void
    {
        $original = (new FakeAdminCommand())->getFactory();
        $replacement = new BillingAdminFactory();

        Gacela::overrideExistingResolvedClass(BillingAdminFactory::class, $replacement);

        self::assertNotSame($replacement, $original);
        self::assertSame($replacement, (new FakeAdminCommand())->getFactory());
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function namedPillars(): iterable
    {
        yield 'facade with conventional sibling' => ['getAdmin', BillingAdminFacade::class];
        yield 'facade without conventional sibling' => ['getExport', ReportsExportFacade::class];
        yield 'factory' => ['getFactory', BillingAdminFactory::class];
        yield 'config' => ['getConfig', BillingAdminConfig::class];
        yield 'conventional facade' => ['getFacade', BillingFacade::class];
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
     * The normalization is memoized, and asserting the value cannot show it:
     * the computation is pure, so a build that recomputes on every call returns
     * exactly the same string. Only reading the memo is observable, so the test
     * writes a sentinel into it and asks whether the next call comes back with
     * that -- which it can only do by consulting the memo rather than the class.
     */
    public function test_the_normalized_type_is_read_from_the_memo_not_recomputed(): void
    {
        $first = DocBlockResolver::fromClassName(FakeFacade::class)->getDocBlockResolvable('getFactory');

        // Keyed by the name as resolved, which the use-statement scan hands
        // back with a leading `\` -- so the key is taken from the answer rather
        // than spelled here.
        $this->pokeNormalizedType($first->className(), 'SentinelKind');

        self::assertSame(
            'SentinelKind',
            DocBlockResolver::fromClassName(FakeFacade::class)
                ->getDocBlockResolvable('getFactory')
                ->resolvableType(),
        );
    }

    /**
     * And the memo is dropped by a cache reset, so the sentinel above cannot
     * outlive one -- a memo nothing clears is the other half of the bargain.
     */
    public function test_a_cache_reset_drops_the_memoized_normalization(): void
    {
        $first = DocBlockResolver::fromClassName(FakeFacade::class)->getDocBlockResolvable('getFactory');
        $this->pokeNormalizedType($first->className(), 'SentinelKind');

        Gacela::resetCache();
        // resetCache() drops the Config singleton along with the caches, so the
        // next resolve needs a bootstrapped framework to run in at all.
        $this->setUp();

        self::assertSame(
            'Factory',
            DocBlockResolver::fromClassName(FakeFacade::class)
                ->getDocBlockResolvable('getFactory')
                ->resolvableType(),
        );
    }

    public function test_class_without_docblock_throws_missing_definition(): void
    {
        $this->expectException(MissingClassDefinitionException::class);

        DocBlockResolver::fromClassName(FakeNoDocBlockCommand::class)
            ->getDocBlockResolvable('getSomething');
    }

    public function test_internal_class_without_source_file_throws_missing_definition(): void
    {
        $this->expectException(MissingClassDefinitionException::class);

        DocBlockResolver::fromClassName(stdClass::class)
            ->getDocBlockResolvable('getSomething');
    }

    public function test_deleted_source_file_throws_missing_definition(): void
    {
        $file = sys_get_temp_dir() . '/FakeDeletedFileCommand-' . uniqid('', true) . '.php';
        file_put_contents($file, <<<'PHP'
            <?php

            namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

            /**
             * @method \GacelaTest\Unknown\DoesNotExist getGone()
             */
            class FakeDeletedFileCommand
            {
            }
            PHP);
        require $file;
        unlink($file);

        $this->expectException(MissingClassDefinitionException::class);

        /** @var class-string $deletedFileClass */
        $deletedFileClass = 'GacelaTest\Integration\Framework\ServiceResolver\Module\FakeDeletedFileCommand';

        // Silence the expected file_get_contents() warning for the deleted file.
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            DocBlockResolver::fromClassName($deletedFileClass)
                ->getDocBlockResolvable('getGone');
        } finally {
            restore_error_handler();
        }
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

        // A class is a pillar by being one, not by having the word in its name.
        // `FakeConfigurationLoader` contains `Config` and extends nothing, so
        // resolving it as the module's Config pillar handed back an anonymous
        // AbstractConfig instead of the class the attribute names.
        yield 'A custom service whose name merely contains a pillar word' => [
            new FakeLoaderCommand(),
            'getLoader',
            FakeConfigurationLoader::class,
            'FakeConfigurationLoader',
        ];

        // A docblock whose prose names the accessor above the tag that declares
        // it. The prose used to answer for the tag, and the class stopped
        // resolving.
        yield 'Facade resolution past prose naming the accessor' => [
            new FakeProseCommand(),
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

    private function pokeNormalizedType(string $className, string $value): void
    {
        $property = new ReflectionProperty(DocBlockResolver::class, 'normalizedTypes');
        /** @var array<string,string> $memo */
        $memo = $property->getValue();
        $memo[$className] = $value;
        $property->setValue(null, $memo);
    }
}
