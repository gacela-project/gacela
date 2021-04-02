<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Infrastructure\Io;

use Gacela\CodeGenerator\Domain\Io\GeneratorIoInterface;
use RuntimeException;

final class SystemGeneratorIo implements GeneratorIoInterface
{
    public function createDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, $permissions = 0777, $recursive = true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }
    }

    public function fileGetContents(string $filename): string
    {
        if (is_dir($filename)) {
            throw new RuntimeException(sprintf('"%s" is a directory but needs to be a file path', $filename));
        }

        if (!is_file($filename)) {
            throw new RuntimeException(sprintf('File path "%s" not found', $filename));
        }

        return file_get_contents($filename);
    }

    public function filePutContents(string $filename, string $content): void
    {
        file_put_contents($filename, $content);
    }

    public function writeln(string $string = ''): void
    {
        print $string . PHP_EOL;
    }
}
