<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingDeclaredResolvableTypes;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\ClassResolver\ResolvableType;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Exception\ResolvableTypeException;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

/**
 * A kind the project declared, resolved end to end like a pillar: through both
 * finder rules, through the file cache, and through the test-override seam.
 */
final class FeatureTest extends TestCase
{
    private const CACHE_DIR = __DIR__ . '/cache';

    public static function tearDownAfterClass(): void
    {
        Gacela::resetCache();
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    protected function tearDown(): void
    {
        Gacela::resetCache();
        DirectoryUtil::removeDir(self::CACHE_DIR);
    }

    public function test_a_declared_kind_resolves_with_the_module_prefix(): void
    {
        $this->bootstrap();

        self::assertSame('report-exported', (new Report\ReportFactory())->createExportedReport());
    }

    /**
     * The kind's second suffix, on the finder rule that takes no module
     * prefix -- the same rule that finds a bare `Facade`.
     */
    public function test_a_declared_kind_resolves_through_its_other_suffix(): void
    {
        $this->bootstrap();

        self::assertSame('invoice-fed', (new Invoice\InvoiceFactory())->createFedInvoice());
    }

    /**
     * The on-disk resolver cache stores plain string keys, so a declared kind
     * needs no new format -- but nothing proved it round-trips until now.
     */
    public function test_a_declared_kind_survives_the_file_cache(): void
    {
        $this->bootstrap(fileCache: true);
        self::assertSame('report-exported', (new Report\ReportFactory())->createExportedReport());

        Gacela::resetCache();

        $this->bootstrap(fileCache: true);
        self::assertSame('report-exported', (new Report\ReportFactory())->createExportedReport());
    }

    /**
     * The seam the declaration exists to close: an override written against
     * the concrete class name has to land on the key the resolver looks up.
     * Without the declaration `WalletReader`-shaped names split on the last
     * namespace separator instead, and the override was never found.
     */
    public function test_an_override_of_a_declared_kind_is_the_one_resolved(): void
    {
        $this->bootstrap();

        AnonymousGlobal::overrideExistingResolvedClass(
            Report\ReportExporter::class,
            new class() extends AbstractExporter {
                public function export(): string
                {
                    return 'overridden';
                }
            },
        );

        self::assertSame('overridden', (new Report\ReportFactory())->createExportedReport());
    }

    /**
     * `gacela.php` declares `Feed` for Exporter; the bootstrap closure claims
     * it for another kind. Each source validates alone and passes, and the
     * union would name neither -- every `*Feed` silently falling back to the
     * namespace split. The check has to run where the sources meet.
     */
    public function test_two_sources_cannot_claim_one_suffix_between_them(): void
    {
        $this->expectException(ResolvableTypeException::class);
        $this->expectExceptionMessage('Feed');

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false, self::CACHE_DIR);
            $config->addResolvableType('Importer', null, ['Feed']);
        });

        // The declarations meet when the sources are merged, which is the
        // first time anything asks the configuration a question.
        Config::getInstance()->getFactory()->createGacelaFileConfig();
    }

    public function test_a_declared_suffix_names_its_kind(): void
    {
        $this->bootstrap();

        $type = ResolvableType::fromClassName('App\Report\ReportExporter');

        self::assertSame('Exporter', $type->resolvableType());
        self::assertSame('App\Report\Report', $type->moduleName());
    }

    /**
     * Bootstrapping again without the declaration must not leave the previous
     * bootstrap's kinds behind, memos included.
     */
    public function test_a_kind_is_gone_after_a_bootstrap_that_does_not_declare_it(): void
    {
        $this->bootstrap();
        self::assertSame('Exporter', ResolvableType::fromClassName('App\Report\ReportExporter')->resolvableType());

        Gacela::bootstrap(__DIR__ . '/../ExtendConfig', static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });

        self::assertSame('ReportExporter', ResolvableType::fromClassName('App\Report\ReportExporter')->resolvableType());
    }

    private function bootstrap(bool $fileCache = false): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($fileCache): void {
            $config->resetInMemoryCache();
            $config->setFileCache($fileCache, self::CACHE_DIR);
        });
    }
}
