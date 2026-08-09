<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use RuntimeException;

use function array_key_exists;
use function is_file;
use function is_string;
use function sprintf;

/**
 * The template used to generate one file: the project's, if it published one.
 *
 * Resolution is per file, not per template set. A project that published only
 * its Facade stub gets its own Facade and the built-in everything else --
 * publishing one file must not freeze the rest at the version it was copied
 * from.
 */
final class StubLocator
{
    /**
     * @param array<string, string> $builtIn     generated filename => template contents
     * @param array<string, string> $stubFiles   generated filename => stub file, relative to the stubs dir
     */
    public function __construct(
        private readonly string $stubsDir,
        private readonly array $builtIn,
        private readonly array $stubFiles,
    ) {
    }

    public function templateFor(string $filename): string
    {
        $published = $this->publishedTemplate($filename);
        if ($published !== null) {
            return $published;
        }

        if (!array_key_exists($filename, $this->builtIn)) {
            throw new RuntimeException(sprintf("Unknown template for '%s'?", $filename));
        }

        return $this->builtIn[$filename];
    }

    private function publishedTemplate(string $filename): ?string
    {
        if ($this->stubsDir === '' || !array_key_exists($filename, $this->stubFiles)) {
            return null;
        }

        $path = $this->stubsDir . '/' . $this->stubFiles[$filename];
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : null;
    }
}
