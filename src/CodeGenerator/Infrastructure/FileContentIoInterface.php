<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Infrastructure;

interface FileContentIoInterface
{
    public function mkdir(string $directory): void;

    public function filePutContents(string $path, string $fileContent): void;
}
