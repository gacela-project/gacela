<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\FilenameSanitizer;

use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function sprintf;

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
     * Undeclared, the word reaches nothing.
     *
     * It used to fall back to the closest pillar, so `make:file App/Wallet
     * Exporter` wrote a `Provider` and reported it as created. The words that
     * land here are the ones people actually type -- `Repository`, `Controller`,
     * `Service`, `Migration` -- and every one of them produced a file of a kind
     * nobody asked for.
     */
    public function test_an_undeclared_kind_reaches_nothing(): void
    {
        $this->expectExceptionMessage('"Exporter" is not one of the filenames make:file can generate');

        $this->filenameSanitizer->sanitize('Exporter');
    }

    /**
     * Naming the way out: Gacela has a feature for exactly this, and a reader
     * who typed `Repository` almost certainly wants it.
     */
    public function test_the_refusal_points_at_declaring_the_kind(): void
    {
        $this->expectExceptionMessage("addResolvableType('Repository')");

        $this->filenameSanitizer->sanitize('Repository');
    }

    /**
     * The kinds every other PHP framework has, which Gacela does not. Each one
     * silently produced a pillar: `Repository` and `Migration` a `Factory`,
     * `Controller`, `Service` and `Middleware` a `Provider`.
     *
     * @return iterable<string, array{string}>
     */
    public static function kindsGacelaDoesNotHave(): iterable
    {
        yield 'repository' => ['Repository'];
        yield 'controller' => ['Controller'];
        yield 'service' => ['Service'];
        yield 'middleware' => ['Middleware'];
        yield 'migration' => ['Migration'];
        yield 'model' => ['Model'];
        yield 'entity' => ['Entity'];
        yield 'listener' => ['Listener'];
        yield 'command' => ['Command'];
        yield 'handler' => ['Handler'];
    }

    #[DataProvider('kindsGacelaDoesNotHave')]
    public function test_a_kind_gacela_does_not_have_is_refused(string $filename): void
    {
        $this->expectExceptionMessage(sprintf('"%s" is not one of the filenames make:file can generate', $filename));

        $this->filenameSanitizer->sanitize($filename);
    }

    /**
     * `dependency-provider` is what `Provider` used to be called, and `de-pr`
     * abbreviates that rather than `Provider` -- so the old name is carried as
     * an alias instead of being left to a similarity score that cannot tell it
     * from `Service`.
     */
    public function test_the_provider_still_answers_to_its_former_name(): void
    {
        self::assertSame(FilenameSanitizer::PROVIDER, $this->filenameSanitizer->sanitize('dependency-provider'));
        self::assertSame(FilenameSanitizer::PROVIDER, $this->filenameSanitizer->sanitize('de-pr'));
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
