<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use Gacela\Console\Domain\CommandArguments\CommandArguments;

use function sprintf;

final class FileContentGenerator implements FileContentGeneratorInterface
{
    public function __construct(
        private readonly FileContentIoInterface $fileContentIo,
        private readonly StubLocator $stubs,
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

        $fileContent = str_replace($search, $replace, $this->stubs->templateFor($filename));

        $this->fileContentIo->filePutContents($path, $fileContent);

        return $path;
    }
}
