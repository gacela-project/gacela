<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\FileContent;

use Gacela\Console\Domain\CommandArguments\CommandArguments;
use Gacela\Console\Domain\FileContent\FileContentGenerator;
use Gacela\Console\Domain\FileContent\FileContentIoInterface;
use Gacela\Console\Domain\FileContent\StubFiles;
use Gacela\Console\Domain\FileContent\StubLocator;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileContentGeneratorTest extends TestCase
{
    public function test_error_when_unknown_template(): void
    {
        $fileContentIo = $this->createStub(FileContentIoInterface::class);
        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [], StubFiles::basic()));

        $this->expectExceptionMessage("Unknown template for 'unknown_template'?");
        $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            'unknown_template',
        );
    }

    /**
     * The path the overwrite check reports, spelled out for every shape that
     * changes it.
     *
     * Deliberately literal rather than compared against what `generate()`
     * writes: `generate()` builds its path by calling this method, so asserting
     * the two agree asserts nothing. Agreement is a property of the design --
     * one path builder, used by both -- and what remains worth pinning is that
     * the builder is right.
     *
     * @param list<string> $expected
     */
    #[DataProvider('pathShapes')]
    public function test_the_reported_target_covers_every_path_shape(bool $shortName, string $subDirectory, string $expected): void
    {
        $generator = new FileContentGenerator(
            $this->createStub(FileContentIoInterface::class),
            new StubLocator('', ['Facade' => 'template-result'], StubFiles::basic()),
        );

        $actual = $generator->targetPath(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::FACADE,
            $shortName,
            $subDirectory,
        );

        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{bool, string, string}>
     */
    public static function pathShapes(): iterable
    {
        yield 'plain' => [false, '', 'Dir/DirFacade.php'];
        yield 'short name' => [true, '', 'Dir/Facade.php'];
        yield 'sub-directory' => [false, 'Domain', 'Dir/Domain/DirFacade.php'];
        yield 'short name in a sub-directory' => [true, 'Domain', 'Dir/Domain/Facade.php'];
    }

    /**
     * Naming a target must not create the directory for it: a command asks this
     * before deciding whether to refuse, and a refused run that left an empty
     * module directory behind would be writing after saying it wrote nothing.
     */
    public function test_naming_a_target_creates_nothing(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::never())->method('mkdir');
        $fileContentIo->expects(self::never())->method('filePutContents');

        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [], StubFiles::basic()));

        $generator->targetPath(new CommandArguments('Namespace', 'Dir'), FilenameSanitizer::FACADE);
    }

    public function test_facade_maker_template(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/DirFacade.php', 'template-result');

        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [
            'Facade' => 'template-result',
        ], StubFiles::basic()));

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::FACADE,
        );

        self::assertSame('Dir/DirFacade.php', $actualPath);
    }

    public function test_facade_maker_template_with_short_name(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/Facade.php', 'template-result');

        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [
            'Facade' => 'template-result',
        ], StubFiles::basic()));

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::FACADE,
            withShortName: true,
        );

        self::assertSame('Dir/Facade.php', $actualPath);
    }

    public function test_factory_maker_template(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/DirFactory.php', 'template-result');

        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [
            'Factory' => 'template-result',
        ], StubFiles::basic()));

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::FACTORY,
        );

        self::assertSame('Dir/DirFactory.php', $actualPath);
    }

    public function test_factory_maker_template_with_short_name(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/Factory.php', 'template-result');

        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [
            'Factory' => 'template-result',
        ], StubFiles::basic()));

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::FACTORY,
            withShortName: true,
        );

        self::assertSame('Dir/Factory.php', $actualPath);
    }

    public function test_config_maker_template(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/DirConfig.php', 'template-result');

        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [
            'Config' => 'template-result',
        ], StubFiles::basic()));

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::CONFIG,
        );

        self::assertSame('Dir/DirConfig.php', $actualPath);
    }

    public function test_config_maker_template_with_short_name(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/Config.php', 'template-result');

        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [
            'Config' => 'template-result',
        ], StubFiles::basic()));

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::CONFIG,
            withShortName: true,
        );

        self::assertSame('Dir/Config.php', $actualPath);
    }

    public function test_dependency_provider_maker_template(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/DirProvider.php', 'template-result');

        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [
            'Provider' => 'template-result',
        ], StubFiles::basic()));

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::PROVIDER,
        );

        self::assertSame('Dir/DirProvider.php', $actualPath);
    }

    public function test_dependency_provider_maker_template_with_short_name(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/Provider.php', 'template-result');

        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [
            'Provider' => 'template-result',
        ], StubFiles::basic()));

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::PROVIDER,
            withShortName: true,
        );

        self::assertSame('Dir/Provider.php', $actualPath);
    }

    public function test_replaces_all_template_tokens(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('src/Module');

        $expectedContent = 'namespace Namespace\Module; class ModuleFacade extends ModuleBase {}';

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('src/Module/ModuleFacade.php', $expectedContent);

        $generator = new FileContentGenerator($fileContentIo, new StubLocator('', [
            FilenameSanitizer::FACADE => 'namespace $NAMESPACE$; class $CLASS_NAME$ extends $MODULE_NAME$Base {}',
        ], StubFiles::basic()));

        $actualPath = $generator->generate(
            new CommandArguments('Namespace\Module', 'src/Module'),
            FilenameSanitizer::FACADE,
        );

        self::assertSame('src/Module/ModuleFacade.php', $actualPath);
    }
}
