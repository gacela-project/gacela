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

    /**
     * @dataProvider providerFactory
     */
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

    /**
     * @dataProvider providerConfig
     */
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

    /**
     * @dataProvider provideProvider
     */
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
