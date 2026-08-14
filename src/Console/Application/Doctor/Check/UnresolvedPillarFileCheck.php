<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Closure;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use ReflectionClass;

use function array_unique;
use function array_values;
use function basename;
use function dirname;
use function is_file;
use function sprintf;

/**
 * Reports a pillar file that is on disk and resolved to nothing.
 *
 * A module whose `BlogFactory.php` cannot be loaded -- a namespace that
 * disagrees with the psr-4 prefix, most often -- is still a module: the Facade
 * resolves, discovery keeps it, and the Factory simply comes back `null`. So
 * `list:modules` prints a blank cell, `debug:module` says `(not found)`, and
 * the reader is told they have no Factory while looking at the file they
 * wrote.
 *
 * Nothing else reports it. `FilenameMismatchCheck` scans the same directory
 * but compares the declared class name against the filename, and those agree
 * here -- the namespace is what is wrong. The undiscovered-facades check
 * answers the same question one level up, for the Facade that would have made
 * the whole module vanish.
 *
 * @psalm-import-type SuffixTypes from SuffixTypesBuilder
 */
final class UnresolvedPillarFileCheck implements HealthCheck
{
    /** @var Closure(class-string):?string */
    private readonly Closure $fileResolver;

    /**
     * @param list<AppModule> $modules
     * @param SuffixTypes $suffixTypes kind => suffixes; a project-declared kind is an ordinary key
     * @param null|Closure(class-string):?string $fileResolver resolves a class-name to its source file path
     */
    public function __construct(
        private readonly array $modules,
        private readonly array $suffixTypes = [],
        ?Closure $fileResolver = null,
    ) {
        $this->fileResolver = $fileResolver ?? static function (string $className): ?string {
            /** @var class-string $className the caller passes a resolved Facade */
            $file = (new ReflectionClass($className))->getFileName();

            return $file === false ? null : $file;
        };
    }

    public function name(): string
    {
        return 'unresolved pillar files';
    }

    public function run(): CheckResult
    {
        $unresolved = [];

        foreach ($this->modules as $module) {
            foreach ($this->unresolvedFilesOf($module) as $detail) {
                $unresolved[] = $detail;
            }
        }

        if ($unresolved === []) {
            return CheckResult::ok($this->name(), 'every pillar file on disk resolved');
        }

        return CheckResult::error(
            $this->name(),
            $unresolved,
            'the file is there and nothing can load it — check the `namespace` declaration '
            . 'matches the psr-4 prefix for its directory, then `composer dump-autoload`',
        );
    }

    /**
     * @return list<string>
     */
    private function unresolvedFilesOf(AppModule $module): array
    {
        $directory = $this->directoryOf($module);
        if ($directory === null) {
            return [];
        }

        $unresolved = [];

        // The Facade is not asked about: a module whose Facade did not resolve
        // is not in this list at all, and the undiscovered-facades check is
        // what answers for it.
        $pillars = [
            ResolvableTypes::FACTORY => $module->factoryClass(),
            ResolvableTypes::CONFIG => $module->configClass(),
            ResolvableTypes::PROVIDER => $module->providerClass(),
        ];

        foreach ($pillars as $kind => $resolved) {
            if ($resolved !== null) {
                continue;
            }

            foreach ($this->candidateFilesFor($module, $directory, $kind) as $file) {
                if (is_file($file)) {
                    $unresolved[] = sprintf('%s — %s is on disk and no %s resolved', $module->fullModuleName(), basename($file), $kind);
                }
            }
        }

        return $unresolved;
    }

    /**
     * The names the scaffolder writes for this kind: `BlogFactory.php`, and
     * `Factory.php` for a `--short-name` module, under every configured suffix.
     *
     * @return list<string>
     */
    private function candidateFilesFor(AppModule $module, string $directory, string $kind): array
    {
        $files = [];

        foreach ($this->suffixesOf($kind) as $suffix) {
            $files[] = $directory . DIRECTORY_SEPARATOR . $module->moduleName() . $suffix . '.php';
            $files[] = $directory . DIRECTORY_SEPARATOR . $suffix . '.php';
        }

        // No dedup here: the suffixes arrive unique, so two entries can only
        // differ. Deduping in both places made each one invisible -- removing
        // either alone changed nothing, because the other still covered it.
        return $files;
    }

    /**
     * @return list<string>
     */
    private function suffixesOf(string $kind): array
    {
        // Indexed rather than defaulted: only the three built-in pillar kinds
        // reach here, and a fallback for a kind that cannot arrive is a branch
        // no test can reach.
        $suffixes = ResolvableTypes::BUILT_IN[$kind];

        foreach ($this->suffixTypes[$kind] ?? [] as $suffix) {
            $suffixes[] = $suffix;
        }

        return array_values(array_unique($suffixes));
    }

    private function directoryOf(AppModule $module): ?string
    {
        $facadeFile = ($this->fileResolver)($module->facadeClass());

        return $facadeFile === null ? null : dirname($facadeFile);
    }
}
