<?php

declare(strict_types=1);

namespace Gacela\Console\Application\DtoGenerate;

use Gacela\Console\Domain\DtoGenerate\DtoClassBuilder;
use Gacela\Console\Domain\DtoGenerate\DtoGenerateResult;
use Gacela\Console\Domain\DtoGenerate\GeneratedClassPath;
use Gacela\Console\Domain\FileContent\FileContentIoInterface;
use Gacela\Framework\Dto\Schema\DtoSchema;

use function dirname;
use function file_get_contents;
use function is_file;

final class DtoGenerator
{
    public function __construct(
        private readonly DtoClassBuilder $classBuilder,
        private readonly GeneratedClassPath $classPath,
        private readonly FileContentIoInterface $io,
    ) {
    }

    public function generate(DtoSchema $schema, bool $dryRun): DtoGenerateResult
    {
        $written = [];
        $unchanged = [];
        $unplaceable = [];

        foreach ($schema->shapes() as $className => $properties) {
            $file = $this->classPath->fileFor($className);

            if ($file === null) {
                $unplaceable[] = $className;
                continue;
            }

            $contents = $this->classBuilder->build($className, $properties);

            if ($this->currentContents($file) === $contents) {
                $unchanged[] = $className;
                continue;
            }

            if (!$dryRun) {
                $this->io->mkdir(dirname($file));
                $this->io->filePutContents($file, $contents);
            }

            $written[$className] = $file;
        }

        return new DtoGenerateResult($written, $unchanged, $unplaceable);
    }

    private function currentContents(string $file): ?string
    {
        if (!is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        return $contents === false ? null : $contents;
    }
}
