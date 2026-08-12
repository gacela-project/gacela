<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\FileContent;

use RuntimeException;

use function array_key_exists;
use function in_array;
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
     * @param list<string> $declaredKinds        the kinds the project declared, which ship no template of their own
     */
    public function __construct(
        private readonly string $stubsDir,
        private readonly array $builtIn,
        private readonly array $stubFiles,
        private readonly array $declaredKinds = [],
    ) {
    }

    public function templateFor(string $filename): string
    {
        $published = $this->publishedTemplate($filename);
        if ($published !== null) {
            return $published;
        }

        if (!array_key_exists($filename, $this->builtIn)) {
            throw new RuntimeException($this->noTemplateMessage($filename));
        }

        return $this->builtIn[$filename];
    }

    /**
     * Nothing ships for a kind the project declared, so the only useful answer
     * names the file that is missing rather than calling the kind unknown. Any
     * other filename without a template is unknown in the old sense -- a pillar
     * gets here only when its provided template map is malformed, and telling
     * that reader to publish a stub would send them the wrong way.
     */
    private function noTemplateMessage(string $filename): string
    {
        if (!in_array($filename, $this->declaredKinds, true) || !array_key_exists($filename, $this->stubFiles)) {
            return sprintf("Unknown template for '%s'?", $filename);
        }

        $expected = $this->stubsDir === ''
            ? $this->stubFiles[$filename]
            : $this->stubsDir . '/' . $this->stubFiles[$filename];

        return sprintf(
            "No template for '%s'. Nothing ships for a kind you declared: write its stub at %s",
            $filename,
            $expected,
        );
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
