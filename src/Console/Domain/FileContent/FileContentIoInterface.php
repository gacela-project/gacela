<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

interface FileContentIoInterface
{
    public function mkdir(string $directory): void;

    public function filePutContents(string $path, string $fileContent): void;
}
