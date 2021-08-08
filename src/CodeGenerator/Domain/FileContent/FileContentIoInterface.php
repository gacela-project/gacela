<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\FileContent;

interface FileContentIoInterface
{
    public function mkdir(string $directory): void;

    public function filePutContents(string $path, string $fileContent): void;
}
