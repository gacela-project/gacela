<?php

declare(strict_types=1);

namespace GacelaTest\Unit\CodeGenerator\Domain\FileContent;

use Gacela\CodeGenerator\Domain\CommandArguments\CommandArguments;
use Gacela\CodeGenerator\Domain\FileContent\FileContentGenerator;
use Gacela\CodeGenerator\Domain\FileContent\FileContentIoInterface;
use Gacela\CodeGenerator\Domain\FilenameSanitizer\FilenameSanitizer;
use PHPUnit\Framework\TestCase;

final class FileContentGeneratorTest extends TestCase
{
    public function test_error_when_unknown_template(): void
    {
        $fileContentIo = $this->createStub(FileContentIoInterface::class);
        $generator = new FileContentGenerator($fileContentIo, []);

        $this->expectExceptionMessage("Unknown template for 'unknown_template'?");
        $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            'unknown_template'
        );
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

        $generator = new FileContentGenerator($fileContentIo, [
            'Facade' => 'template-result',
        ]);

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::FACADE
        );

        self::assertSame('Dir/DirFacade.php', $actualPath);
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

        $generator = new FileContentGenerator($fileContentIo, [
            'Factory' => 'template-result',
        ]);

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::FACTORY
        );

        self::assertSame('Dir/DirFactory.php', $actualPath);
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

        $generator = new FileContentGenerator($fileContentIo, [
            'Config' => 'template-result',
        ]);

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::CONFIG
        );

        self::assertSame('Dir/DirConfig.php', $actualPath);
    }

    public function test_dependency_provider_maker_template(): void
    {
        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/DirDependencyProvider.php', 'template-result');

        $generator = new FileContentGenerator($fileContentIo, [
            'DependencyProvider' => 'template-result',
        ]);

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::DEPENDENCY_PROVIDER
        );

        self::assertSame('Dir/DirDependencyProvider.php', $actualPath);
    }
}
