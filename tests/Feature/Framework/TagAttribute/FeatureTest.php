<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TagAttribute;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use Gacela\Framework\Plugins\Membership\Members;
use Gacela\Framework\Plugins\Membership\MembershipCache;
use Gacela\Framework\Plugins\Membership\TagMember;
use GacelaTest\Feature\Framework\TagAttribute\Export\Central;
use GacelaTest\Feature\Framework\TagAttribute\Export\Csv;
use GacelaTest\Feature\Framework\TagAttribute\Export\ExportFacade;
use GacelaTest\Feature\Framework\TagAttribute\Export\Untagged;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_file;
use function iterator_to_array;
use function unlink;

/**
 * A class joins a tag by carrying `#[Tag]`, and whoever reads the tag sees it
 * after the ids `gacela.php` tagged.
 */
final class FeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_attribute_members_follow_the_tagged_ids_by_class_name(): void
    {
        $this->bootstrap([Central::class]);

        self::assertSame(['central', 'avro', 'csv', 'json'], (new ExportFacade())->exporterNames());
    }

    public function test_a_tag_nothing_declares_is_filled_by_attributes_alone(): void
    {
        $this->bootstrap([]);

        self::assertSame(['avro', 'csv', 'json'], (new ExportFacade())->exporterNames());
    }

    public function test_a_class_tagged_both_ways_appears_once(): void
    {
        $this->bootstrap([Csv::class, Central::class]);

        self::assertSame(['csv', 'central', 'avro', 'json'], (new ExportFacade())->exporterNames());
    }

    public function test_the_application_container_reads_attribute_tags_too(): void
    {
        $this->bootstrap([]);

        self::assertSame(['json'], $this->namesOf(Gacela::container()->tagged('reports')));
    }

    /**
     * A module's own `tag()` comes after the attribute members, whichever
     * container read the tag first.
     */
    public function test_a_scope_tags_after_the_attribute_members_whoever_reads_first(): void
    {
        $this->bootstrap([Central::class]);
        $expected = ['central', 'avro', 'csv', 'json', 'untagged'];

        $readFirst = Gacela::container()->createScope();
        $readFirst->tag(Untagged::class, 'exporters');
        self::assertSame($expected, self::namesOf($readFirst->tagged('exporters')));

        Gacela::container()->tagged('exporters');
        $readAfterTheRoot = Gacela::container()->createScope();
        $readAfterTheRoot->tag(Untagged::class, 'exporters');
        self::assertSame($expected, self::namesOf($readAfterTheRoot->tagged('exporters')));
    }

    public function test_a_cache_warmed_before_tags_existed_reads_as_no_tags(): void
    {
        $this->bootstrap([Central::class], fileCache: true);
        $cache = self::cache();
        file_put_contents($cache->path(), "<?php return ['plugins' => []];");

        try {
            self::assertSame(['central'], (new ExportFacade())->exporterNames());
        } finally {
            unlink($cache->path());
        }
    }

    public function test_a_warmed_cache_is_read_instead_of_scanning(): void
    {
        $this->bootstrap([Central::class], fileCache: true);
        $cache = $this->cache();
        $cache->write(new Members([], [new TagMember('exporters', Untagged::class)]));

        try {
            self::assertSame(['central', 'untagged'], (new ExportFacade())->exporterNames());
        } finally {
            unlink($cache->path());
        }
    }

    public function test_with_file_caching_on_the_first_scan_is_stored(): void
    {
        $this->bootstrap([], fileCache: true);
        $cache = $this->cache();

        try {
            self::assertFileDoesNotExist($cache->path());
            (new ExportFacade())->exporterNames();
            self::assertCount(4, $cache->read()?->tags ?? []);
        } finally {
            if (is_file($cache->path())) {
                unlink($cache->path());
            }
        }
    }

    /**
     * @param iterable<mixed> $exporters
     *
     * @return list<string>
     */
    private function namesOf(iterable $exporters): array
    {
        $names = [];
        foreach (iterator_to_array($exporters, false) as $exporter) {
            self::assertInstanceOf(Export\Exporter::class, $exporter);
            $names[] = $exporter->name();
        }

        return $names;
    }

    private function cache(): MembershipCache
    {
        $config = Config::getInstance();

        return MembershipCache::forScan($config->getCacheDir(), $config->getAppRootDir(), $config->getSetupGacela()->getAppModulePaths(), $config->getSetupGacela()->getProjectNamespaces());
    }

    /**
     * @param list<class-string> $tagged
     */
    private function bootstrap(array $tagged, bool $fileCache = false): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($tagged, $fileCache): void {
            $config->resetInMemoryCache();
            $config->setFileCache($fileCache);
            $config->setProjectNamespaces([__NAMESPACE__ . '\Export']);
            if ($tagged !== []) {
                $config->tag($tagged, 'exporters');
            }
        });
    }
}
