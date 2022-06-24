<?php

declare(strict_types=1);

namespace GacelaTest\Unit\CodeGenerator\Domain\FilenameSanitizer;

use Gacela\CodeGenerator\Domain\FilenameSanitizer\FilenameSanitizer;
use PHPUnit\Framework\TestCase;

final class FilenameSanitizerTest extends TestCase
{
    private FilenameSanitizer $filenameSanitizer;

    public function setUp(): void
    {
        $this->filenameSanitizer = new FilenameSanitizer();
    }

    public function test_expected_filenames(): void
    {
        $actual = implode(', ', $this->filenameSanitizer->getExpectedFilenames());

        self::assertSame('Facade, Factory, Config, DependencyProvider', $actual);
    }

    public function test_facade_or_factory_problem(): void
    {
        $this->expectExceptionMessage('When using "fac", which filename do you mean [Facade or Factory]?');
        $this->filenameSanitizer->sanitize('fac');
    }

    /**
     * @dataProvider providerFacade
     */
    public function test_facade(string $filename): void
    {
        self::assertSame(
            FilenameSanitizer::FACADE,
            $this->filenameSanitizer->sanitize($filename)
        );
    }

    public function providerFacade(): iterable
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
            $this->filenameSanitizer->sanitize($filename)
        );
    }

    public function providerFactory(): iterable
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
            $this->filenameSanitizer->sanitize($filename)
        );
    }

    public function providerConfig(): iterable
    {
        yield ['conf'];
        yield ['confi'];
        yield ['config'];
        yield ['Config'];
        yield ['fig'];
    }

    /**
     * @dataProvider providerDependencyProvider
     */
    public function test_dependency_provider(string $filename): void
    {
        self::assertSame(
            FilenameSanitizer::DEPENDENCY_PROVIDER,
            $this->filenameSanitizer->sanitize($filename)
        );
    }

    public function providerDependencyProvider(): iterable
    {
        yield ['depe'];
        yield ['dependency'];
        yield ['pro'];
        yield ['provider'];
        yield ['de-pr'];
        yield ['dependencyprovider'];
        yield ['dependency-provider'];
    }
}
