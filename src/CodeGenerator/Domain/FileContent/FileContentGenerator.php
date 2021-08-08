<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\FileContent;

use Gacela\CodeGenerator\Domain\CommandArguments\CommandArguments;
use RuntimeException;

final class FileContentGenerator implements FileContentGeneratorInterface
{
    private FileContentIoInterface $fileContentIo;

    /** @var array<string,string> */
    private array $templateByFilenameMap;

    /**
     * @param array<string,string> $templateByFilenameMap
     */
    public function __construct(
        FileContentIoInterface $fileContentIo,
        array $templateByFilenameMap
    ) {
        $this->fileContentIo = $fileContentIo;
        $this->templateByFilenameMap = $templateByFilenameMap;
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

        $template = $this->templateByFilenameMap[$filename] ?? '';
        if (empty($template)) {
            throw new RuntimeException("Unknown template for '$filename'?");
        }

        $fileContent = str_replace($search, $replace, $template);

        $this->fileContentIo->filePutContents($path, $fileContent);

        return $path;
    }
}
