<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use Gacela\Console\Domain\CommandArguments\CommandArguments;
use RuntimeException;

use function sprintf;

final class FileContentGenerator implements FileContentGeneratorInterface
{
    /**
     * @param array<string,string> $templateByFilenameMap
     */
    public function __construct(
        private readonly FileContentIoInterface $fileContentIo,
        private array $templateByFilenameMap,
    ) {
    }

    /**
     * @param string $subDirectory optional sub-directory (relative to the module dir) to place the file in
     *
     * @return string path result where the file was generated
     */
    public function generate(CommandArguments $commandArguments, string $filename, bool $withShortName = false, string $subDirectory = ''): string
    {
        $targetDirectory = $commandArguments->directory();
        if ($subDirectory !== '') {
            $targetDirectory .= '/' . $subDirectory;
        }

        $this->fileContentIo->mkdir($targetDirectory);

        $moduleName = $withShortName ? '' : $commandArguments->basename();
        $className = $moduleName . $filename;

        $path = sprintf('%s/%s.php', $targetDirectory, $className);
        $search = ['$NAMESPACE$', '$MODULE_NAME$', '$CLASS_NAME$'];
        $replace = [$commandArguments->namespace(), $moduleName, $className];

        $template = $this->templateByFilenameMap[$filename] ?? '';
        if ($template === '') {
            throw new RuntimeException(sprintf("Unknown template for '%s'?", $filename));
        }

        $fileContent = str_replace($search, $replace, $template);

        $this->fileContentIo->filePutContents($path, $fileContent);

        return $path;
    }
}
