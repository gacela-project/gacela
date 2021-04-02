<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Io;

interface MakerIoInterface
{
    public function createDirectory(string $directory): void;

    public function filePutContents(string $filename, string $content): void;

    public function writeln(string $string): void;
}
