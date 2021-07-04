<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain;

use Gacela\CodeGenerator\Infrastructure\FileContentIoInterface;
use Gacela\CodeGenerator\Infrastructure\Template\CodeTemplateInterface;
use RuntimeException;

final class FileContentGenerator
{
    private CodeTemplateInterface $codeTemplate;
    private FileContentIoInterface $fileContentIo;

    public function __construct(
        CodeTemplateInterface $codeTemplate,
        FileContentIoInterface $fileContentIo
    ) {
        $this->codeTemplate = $codeTemplate;
        $this->fileContentIo = $fileContentIo;
    }

    /**
     * @return string path result where the file was generated
     */
    public function generate(CommandArguments $commandArguments, string $filename, bool $withShortName = false): string
    {
        $this->fileContentIo->mkdir($commandArguments->directory());

        $moduleName = $withShortName ? '' : $commandArguments->basename();
        $className = $moduleName . $filename;

        $path = sprintf('%s/%s.php', $commandArguments->directory(), $className);
        $search = ['$NAMESPACE$', '$MODULE_NAME$', '$CLASS_NAME$'];
        $replace = [$commandArguments->namespace(), $moduleName, $className];

        $template = $this->findTemplate($filename);
        $fileContent = str_replace($search, $replace, $template);

        $this->fileContentIo->filePutContents($path, $fileContent);

        return $path;
    }

    private function findTemplate(string $filename): string
    {
        switch ($filename) {
            case FilenameSanitizer::FACADE:
                return $this->codeTemplate->getFacadeMakerTemplate();
            case FilenameSanitizer::FACTORY:
                return $this->codeTemplate->getFactoryMakerTemplate();
            case FilenameSanitizer::CONFIG:
                return $this->codeTemplate->getConfigMakerTemplate();
            case FilenameSanitizer::DEPENDENCY_PROVIDER:
                return $this->codeTemplate->getDependencyProviderMakerTemplate();
            default:
                throw new RuntimeException(sprintf(
                    'Unknown template for "%s"?',
                    $filename
                ));
        }
    }
}
