<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\DtoGenerate;

use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Where a generated class file belongs, according to the project's own
 * composer `autoload`.
 *
 * This is what lets the framework register no autoloader. The file lands where
 * the project already told composer to look for that namespace, so the class is
 * loadable by the setup that was there before, and static analysis reads it
 * without a generation step running first.
 *
 * The alternative -- a directory of the framework's choosing plus an autoloader
 * for a reserved prefix -- puts generated code somewhere the cache commands
 * clear and the environment varies, and makes the analyser's view depend on
 * pipeline order.
 */
final class GeneratedClassPath
{
    /**
     * @param array<string, string> $psr4Prefixes prefix => directory, relative to the root
     */
    public function __construct(
        private readonly array $psr4Prefixes,
        private readonly string $rootDir,
    ) {
    }

    /**
     * Null when no autoload prefix covers the class: the project has to say
     * where that namespace lives before anything can be written for it.
     */
    public function fileFor(string $className): ?string
    {
        $bestPrefix = '';
        $bestDirectory = null;

        foreach ($this->psr4Prefixes as $prefix => $directory) {
            if (!str_starts_with($className, $prefix)) {
                continue;
            }

            // Longest prefix wins, the way composer resolves it: a project with
            // both `App\` and `App\Generated\` means the second for a class
            // under it.
            if (strlen($prefix) > strlen($bestPrefix)) {
                $bestPrefix = $prefix;
                $bestDirectory = $directory;
            }
        }

        if ($bestDirectory === null) {
            return null;
        }

        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($className, strlen($bestPrefix)));

        return $this->rootDir
            . DIRECTORY_SEPARATOR . trim($bestDirectory, '/\\')
            . DIRECTORY_SEPARATOR . $relative . '.php';
    }
}
