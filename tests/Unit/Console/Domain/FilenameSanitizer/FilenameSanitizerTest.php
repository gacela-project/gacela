<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\FilenameSanitizer;

use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FilenameSanitizerTest extends TestCase
{
    private FilenameSanitizer $filenameSanitizer;

    protected function setUp(): void
    {
        $this->filenameSanitizer = new FilenameSanitizer();
    }

    public function test_expected_filenames(): void
    {
        $actual = implode(', ', $this->filenameSanitizer->getExpectedFilenames());

        self::assertSame('Facade, Factory, Config, Provider', $actual);
    }

    public function test_a_declared_kind_joins_the_expected_filenames(): void
    {
        $actual = implode(', ', (new FilenameSanitizer(['Exporter']))->getExpectedFilenames());

        self::assertSame('Facade, Factory, Config, Provider, Exporter', $actual);
    }

    public function test_the_help_text_lists_a_declared_kind(): void
    {
        self::assertSame(
            'Facade, Factory, Config, Provider, Exporter',
            FilenameSanitizer::expectedFilenamesAsText(['Exporter']),
        );
    }

    public function test_a_declared_kind_is_a_filename_of_its_own(): void
    {
        self::assertSame('Exporter', (new FilenameSanitizer(['Exporter']))->sanitize('Exporter'));
    }

    /**
     * A declared kind is matched by the same fuzzy rule as the pillars, so it
     * answers to an abbreviation the way `faca` answers for `Facade`.
     */
    public function test_a_declared_kind_answers_to_an_abbreviation(): void
    {
        self::assertSame('Exporter', (new FilenameSanitizer(['Exporter']))->sanitize('expo'));
    }

    public function test_the_pillars_still_win_the_words_that_are_theirs(): void
    {
        $sanitizer = new FilenameSanitizer(['Exporter']);

        self::assertSame(FilenameSanitizer::FACADE, $sanitizer->sanitize('facade'));
        self::assertSame(FilenameSanitizer::PROVIDER, $sanitizer->sanitize('dependency-provider'));
    }

    /**
     * Undeclared, the word is just another string to fuzzy-match, and it
     * matches what it matched before declared kinds existed.
     */
    public function test_an_undeclared_kind_falls_back_to_the_closest_pillar(): void
    {
        self::assertSame(FilenameSanitizer::PROVIDER, $this->filenameSanitizer->sanitize('Exporter'));
    }

    public function test_facade_or_factory_problem(): void
    {
        $this->expectExceptionMessage('When using "fac", which filename do you mean [Facade or Factory]?');
        $this->filenameSanitizer->sanitize('fac');
    }

    #[DataProvider('providerFacade')]
    public function test_facade(string $filename): void
    {
        self::assertSame(
            FilenameSanitizer::FACADE,
            $this->filenameSanitizer->sanitize($filename),
        );
    }

    public static function providerFacade(): iterable
    {
        yield ['faca'];
        yield ['facad'];
        yield ['facade'];
        yield ['Facade'];
        yield ['cade'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerFactory')]
    public function test_factory(string $filename): void
    {
        self::assertSame(
            FilenameSanitizer::FACTORY,
            $this->filenameSanitizer->sanitize($filename),
        );
    }

    public static function providerFactory(): iterable
    {
        yield ['fact'];
        yield ['facto'];
        yield ['factor'];
        yield ['factory'];
        yield ['Factory'];
        yield ['tory'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerConfig')]
    public function test_config(string $filename): void
    {
        self::assertSame(
            FilenameSanitizer::CONFIG,
            $this->filenameSanitizer->sanitize($filename),
        );
    }

    public static function providerConfig(): iterable
    {
        yield ['conf'];
        yield ['confi'];
        yield ['config'];
        yield ['Config'];
        yield ['fig'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideProvider')]
    public function test_dependency_provider(string $filename): void
    {
        self::assertSame(
            FilenameSanitizer::PROVIDER,
            $this->filenameSanitizer->sanitize($filename),
        );
    }

    public static function provideProvider(): iterable
    {
        yield ['pro'];
        yield ['provider'];
        yield ['de-pr'];
        yield ['provider'];
    }
}
