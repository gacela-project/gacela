<?php

declare(strict_types=1);

namespace GacelaTest\Unit\CodeGenerator\Domain;

use Gacela\CodeGenerator\Domain\CommandArguments;
use Gacela\CodeGenerator\Domain\FileContentGenerator;
use Gacela\CodeGenerator\Domain\FilenameSanitizer;
use Gacela\CodeGenerator\Infrastructure\FileContentIoInterface;
use Gacela\CodeGenerator\Infrastructure\Template\CodeTemplateInterface;
use PHPUnit\Framework\TestCase;

final class FileContentGeneratorTest extends TestCase
{
    public function test_error_when_unknown_template(): void
    {
        $codeTemplate = $this->createStub(CodeTemplateInterface::class);
        $fileContentIo = $this->createStub(FileContentIoInterface::class);
        $generator = new FileContentGenerator($codeTemplate, $fileContentIo);

        $this->expectExceptionMessage('Unknown template for "unknown_template"?');
        $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            'unknown_template'
        );
    }

    public function test_facade_maker_template(): void
    {
        $codeTemplate = $this->createMock(CodeTemplateInterface::class);
        $codeTemplate->expects(self::once())
            ->method('getFacadeMakerTemplate')
            ->willReturn('template-result');

        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/DirFacade.php', 'template-result');

        $generator = new FileContentGenerator($codeTemplate, $fileContentIo);

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::FACADE
        );

        self::assertSame('Dir/DirFacade.php', $actualPath);
    }

    public function test_factory_maker_template(): void
    {
        $codeTemplate = $this->createMock(CodeTemplateInterface::class);
        $codeTemplate->expects(self::once())
            ->method('getFactoryMakerTemplate')
            ->willReturn('template-result');

        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/DirFactory.php', 'template-result');

        $generator = new FileContentGenerator($codeTemplate, $fileContentIo);

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::FACTORY
        );

        self::assertSame('Dir/DirFactory.php', $actualPath);
    }

    public function test_config_maker_template(): void
    {
        $codeTemplate = $this->createMock(CodeTemplateInterface::class);
        $codeTemplate->expects(self::once())
            ->method('getConfigMakerTemplate')
            ->willReturn('template-result');

        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/DirConfig.php', 'template-result');

        $generator = new FileContentGenerator($codeTemplate, $fileContentIo);

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::CONFIG
        );

        self::assertSame('Dir/DirConfig.php', $actualPath);
    }

    public function test_dependency_provider_maker_template(): void
    {
        $codeTemplate = $this->createMock(CodeTemplateInterface::class);
        $codeTemplate->expects(self::once())
            ->method('getDependencyProviderMakerTemplate')
            ->willReturn('template-result');

        $fileContentIo = $this->createMock(FileContentIoInterface::class);
        $fileContentIo->expects(self::once())
            ->method('mkdir')
            ->with('Dir');

        $fileContentIo->expects(self::once())
            ->method('filePutContents')
            ->with('Dir/DirDependencyProvider.php', 'template-result');

        $generator = new FileContentGenerator($codeTemplate, $fileContentIo);

        $actualPath = $generator->generate(
            new CommandArguments('Namespace', 'Dir'),
            FilenameSanitizer::DEPENDENCY_PROVIDER
        );

        self::assertSame('Dir/DirDependencyProvider.php', $actualPath);
    }
}
