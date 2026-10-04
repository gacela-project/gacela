<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use Gacela\Console\Domain\CommandArguments\CommandArguments;

use RuntimeException;

use function preg_match;
use function sprintf;

final class FileContentGenerator implements FileContentGeneratorInterface
{
    public function __construct(
        private readonly FileContentIoInterface $fileContentIo,
        private readonly StubLocator $stubs,
        private readonly string $rootDir = '',
    ) {
    }

    /**
     * Where {@see generate()} would write, without writing it or creating any
     * directory on the way -- so a command can find out what it is about to
     * replace while it is still able to refuse.
     */
    public function targetPath(CommandArguments $commandArguments, string $filename, bool $withShortName, string $subDirectory): string
    {
        $targetDirectory = $commandArguments->directory();
        if ($subDirectory !== '') {
            $targetDirectory .= '/' . $subDirectory;
        }

        $moduleName = $withShortName ? '' : $commandArguments->basename();

        return sprintf('%s/%s%s.php', $targetDirectory, $moduleName, $filename);
    }

    /**
     * @param list<array{string, string}> $files [filename, subDirectory] pairs
     *
     * @return list<string>
     */
    public function existingTargets(CommandArguments $commandArguments, array $files, bool $withShortName): array
    {
        $existing = [];

        foreach ($files as [$filename, $subDirectory]) {
            $path = $this->targetPath($commandArguments, $filename, $withShortName, $subDirectory);

            if ($this->fileContentIo->existsFile($this->onDisk($path))) {
                $existing[] = $path;
            }
        }

        return $existing;
    }

    public function plannedTargets(CommandArguments $commandArguments, array $files, bool $withShortName): array
    {
        $planned = [];

        foreach ($files as [$filename, $subDirectory]) {
            $path = $this->targetPath($commandArguments, $filename, $withShortName, $subDirectory);

            $planned[] = ['path' => $path, 'exists' => $this->fileContentIo->existsFile($this->onDisk($path))];
        }

        return $planned;
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

        $this->fileContentIo->mkdir($this->onDisk($targetDirectory));

        $moduleName = $withShortName ? '' : $commandArguments->basename();
        $className = $moduleName . $filename;

        $path = $this->targetPath($commandArguments, $filename, $withShortName, $subDirectory);
        $search = ['$NAMESPACE$', '$MODULE_NAME$', '$CLASS_NAME$'];
        $replace = [$commandArguments->namespace(), $moduleName, $className];

        $fileContent = str_replace($search, $replace, $this->stubs->templateFor($filename));

        $this->fileContentIo->filePutContents($this->onDisk($path), $fileContent);

        return $path;
    }

    /**
     * A relative psr-4 directory is relative to the project root, which
     * bin/gacela bootstraps from even when it runs in a subdirectory.
     */
    public function missingTemplates(array $filenames): array
    {
        $messages = [];
        foreach ($filenames as $filename) {
            try {
                $this->stubs->templateFor($filename);
            } catch (RuntimeException $runtimeException) {
                $messages[] = $runtimeException->getMessage();
            }
        }

        return $messages;
    }

    private function onDisk(string $path): string
    {
        if ($this->rootDir === '' || preg_match('~^(?:[a-zA-Z]:[\\\\/]|[\\\\/])~', $path) === 1) {
            return $path;
        }

        return $this->rootDir . '/' . $path;
    }
}
