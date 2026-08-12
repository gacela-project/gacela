<?php

declare(strict_types=1);

namespace Gacela\Console\Application\IdeMeta;

use Gacela\Console\Domain\AllAppModules\AllAppModulesFinder;
use Gacela\Console\Domain\FileContent\FileContentIoInterface;
use Gacela\Console\Domain\IdeMeta\IdeMetadataPath;
use Gacela\Console\Domain\IdeMeta\IdeMetadataResult;
use Gacela\Console\Domain\IdeMeta\MetaFileRenderer;

use function count;
use function file_get_contents;
use function is_file;

final class IdeMetadataGenerator
{
    public function __construct(
        private readonly AllAppModulesFinder $modulesFinder,
        private readonly IdeMetadataScanner $scanner,
        private readonly MetaFileRenderer $renderer,
        private readonly FileContentIoInterface $io,
        private readonly string $appRootDir,
    ) {
    }

    public function generate(bool $dryRun): IdeMetadataResult
    {
        // Unfiltered on purpose: the file describes the whole application, and
        // a partial scan would drop every id the filter left out.
        $map = $this->scanner->scan($this->modulesFinder->findAllAppModules(''));
        $content = $this->renderer->render($map);
        $path = IdeMetadataPath::fileIn($this->appRootDir);

        $changed = $this->currentContent($path) !== $content;

        // Identical bytes are not rewritten: it would move the modification
        // time of a file an editor watches, for no change.
        if ($changed && !$dryRun) {
            $this->io->mkdir(IdeMetadataPath::directoryIn($this->appRootDir));
            $this->io->filePutContents($path, $content);
        }

        return new IdeMetadataResult(
            path: $path,
            changed: $changed,
            typedIds: count($map->entries()),
            ambiguous: $map->ambiguous(),
        );
    }

    private function currentContent(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : $content;
    }
}
