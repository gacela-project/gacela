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

    public function test_all_covers_both_sets(): void
    {
        $all = StubFiles::all();

        foreach ([...StubFiles::basic(), ...StubFiles::service()] as $file) {
            self::assertContains($file, $all);
        }

        self::assertCount(count($all), array_unique($all), 'a file listed twice would be published twice');
    }
}
