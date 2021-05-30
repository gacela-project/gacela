<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain;

use Gacela\CodeGenerator\Domain\ReadModel\CommandArguments;
use Gacela\CodeGenerator\Infrastructure\Template\CodeTemplateInterface;
use RuntimeException;

final class FileContentGenerator
{
    private CodeTemplateInterface $codeTemplate;

    public function __construct(CodeTemplateInterface $codeTemplate)
    {
        $this->codeTemplate = $codeTemplate;
    }

    public function generate(CommandArguments $commandArguments, string $filename): void
    {
        $this->mkdir($commandArguments->directory());

        $path = sprintf('%s/%s.php', $commandArguments->directory(), $filename);
        $search = ['$NAMESPACE$', '$CLASS_NAME$'];
        $replace = [$commandArguments->namespace(), $filename];

        $template = $this->findTemplate($filename);
        $fileContent = str_replace($search, $replace, $template);

        file_put_contents($path, $fileContent);
    }

    private function mkdir(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }
        if (!mkdir($directory) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }
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
