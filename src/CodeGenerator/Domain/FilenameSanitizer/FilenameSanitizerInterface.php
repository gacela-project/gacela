<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\FilenameSanitizer;

interface FilenameSanitizerInterface
{
    /**
     * @return list<string>
     */
    public function getExpectedFilenames(): array;

    public function sanitize(string $filename): string;
}
