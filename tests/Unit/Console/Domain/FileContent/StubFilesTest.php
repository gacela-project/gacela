<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\FileContent;

use Gacela\Console\Domain\FileContent\StubFiles;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * The names are the contract in both directions: a stub is published under one
 * and looked up again by the same. Pinning them is what stops a rename from
 * silently publishing a file nothing reads.
 */
final class StubFilesTest extends TestCase
{
    public function test_the_basic_set_names_a_file_for_every_pillar(): void
    {
        self::assertSame([
            FilenameSanitizer::FACADE => 'facade-maker.txt',
            FilenameSanitizer::FACTORY => 'factory-maker.txt',
            FilenameSanitizer::CONFIG => 'config-maker.txt',
            FilenameSanitizer::PROVIDER => 'provider-maker.txt',
        ], StubFiles::basic());
    }

    public function test_the_service_set_has_its_own_facade_and_factory(): void
    {
        $service = StubFiles::service();

        self::assertSame('service/facade-maker.txt', $service[FilenameSanitizer::FACADE]);
        self::assertSame('service/factory-maker.txt', $service[FilenameSanitizer::FACTORY]);
        self::assertSame('service/service-maker.txt', $service['Service']);
        self::assertSame('service/facade-test-maker.txt', $service['FacadeTest']);
    }

    /**
     * Config and Provider are generated the same way by both sets, so they are
     * one file, not two copies that drift.
     */
    public function test_the_sets_share_the_files_they_generate_identically(): void
    {
        self::assertSame(
            StubFiles::basic()[FilenameSanitizer::CONFIG],
            StubFiles::service()[FilenameSanitizer::CONFIG],
        );
        self::assertSame(
            StubFiles::basic()[FilenameSanitizer::PROVIDER],
            StubFiles::service()[FilenameSanitizer::PROVIDER],
        );
    }

    public function test_every_publishable_stub_is_listed_once(): void
    {
        self::assertSame([
            'facade-maker.txt',
            'factory-maker.txt',
            'config-maker.txt',
            'provider-maker.txt',
            'service/facade-maker.txt',
            'service/factory-maker.txt',
            'service/service-maker.txt',
            'service/facade-test-maker.txt',
        ], StubFiles::all());
    }

    public function test_a_declared_kind_names_a_stub_file_of_its_own(): void
    {
        self::assertSame('exporter-maker.txt', StubFiles::basic(['Exporter'])['Exporter']);
    }

    public function test_a_multi_word_kind_is_hyphenated_the_way_the_shipped_names_are(): void
    {
        self::assertSame('event-subscriber-maker.txt', StubFiles::basic(['EventSubscriber'])['EventSubscriber']);
    }

    public function test_a_declared_kind_leaves_the_pillars_where_they_were(): void
    {
        $basic = StubFiles::basic(['Exporter']);

        self::assertSame('facade-maker.txt', $basic[FilenameSanitizer::FACADE]);
        self::assertSame('provider-maker.txt', $basic[FilenameSanitizer::PROVIDER]);
    }

    /**
     * `all()` is what `doctor` reads to tell a stub the scaffolder uses from one
     * nothing reads. A declared kind's stub is used, so it belongs in the list.
     */
    public function test_a_declared_kind_is_a_stub_the_scaffolder_reads(): void
    {
        self::assertContains('exporter-maker.txt', StubFiles::all(['Exporter']));
        self::assertNotContains('exporter-maker.txt', StubFiles::all());
    }

    public function test_all_covers_both_sets(): void
    {
        $all = StubFiles::all();

        foreach ([...StubFiles::basic(), ...StubFiles::service()] as $file) {
            self::assertContains($file, $all);
        }

        self::assertCount(count($all), array_unique($all), 'a file listed twice would be published twice');
    }
}
