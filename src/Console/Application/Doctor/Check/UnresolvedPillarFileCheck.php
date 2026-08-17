<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Closure;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Console\Domain\AllAppModules\PillarResolutionFailure;
use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use ReflectionClass;
use Throwable;

use function array_unique;
use function array_values;
use function basename;
use function class_exists;
use function dirname;
use function is_file;
use function preg_replace;
use function sprintf;
use function trim;

/**
 * Reports a pillar file that is on disk and resolved to nothing.
 *
 * A module whose `BlogFactory.php` did not resolve is still a module: the Facade
 * resolves, discovery keeps it, and the Factory simply comes back `null`. So
 * `list:modules` prints a blank cell, `debug:module` says `(not found)`, and
 * the reader is told they have no Factory while looking at the file they
 * wrote.
 *
 * Two different things end there, and this check used to name only one of them.
 * A namespace disagreeing with the psr-4 prefix is the first; the second is the
 * pillar's own constructor, where an unbound interface throws
 * `DependencyNotFoundException` from a class that loads perfectly. Reporting
 * both as "nothing can load it -- check the `namespace` declaration" sent
 * readers of the second to the one place already known to be fine (#884, #890),
 * so what was thrown is named on the line and the namespace advice waits until
 * the class really is not there.
 *
 * Nothing else reports it. `FilenameMismatchCheck` scans the same directory
 * but compares the declared class name against the filename, and those agree
 * in both cases. The undiscovered-facades check answers the same question one
 * level up, for the Facade that would have made the whole module vanish.
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
        $details = [];
        $everyOneExplained = true;

        foreach ($this->modules as $module) {
            foreach ($this->unresolvedFilesOf($module) as $unresolved) {
                $details[] = $unresolved['detail'];
                $everyOneExplained = $everyOneExplained && $unresolved['explained'];
            }
        }

        if ($details === []) {
            return CheckResult::ok($this->name(), 'every pillar file on disk resolved');
        }

        return CheckResult::error($this->name(), $details, $this->remediationFor($everyOneExplained));
    }

    /**
     * One remediation is printed for the whole finding, so it has to be the one
     * still worth following. Naming the failure is right only when every line
     * has one *and* every class loads; anything else leaves a file that really
     * might not be loadable, and for that the namespace tip is what helps.
     */
    private function remediationFor(bool $everyOneExplained): string
    {
        if ($everyOneExplained) {
            return 'the class loads, so the `namespace` and the psr-4 prefix are not the problem '
                . '— fix the failure named on each line';
        }

        return 'the file is there and nothing can load it — check the `namespace` declaration '
            . 'matches the psr-4 prefix for its directory, then `composer dump-autoload`';
    }

    /**
     * @return list<array{detail: string, explained: bool}>
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

            $failure = $module->resolutionFailure($kind);

            foreach ($this->candidateFilesFor($module, $directory, $kind) as $file) {
                if (!is_file($file)) {
                    continue;
                }

                $unresolved[] = [
                    'detail' => sprintf(
                        '%s — %s is on disk and no %s resolved%s',
                        $module->fullModuleName(),
                        basename($file),
                        $kind,
                        $this->reasonFor($failure),
                    ),
                    'explained' => $failure instanceof PillarResolutionFailure
                        && $this->classLoads($module, $file),
                ];
            }
        }

        return $unresolved;
    }

    /**
     * The thrown class and message, folded onto one line: a detail is one line
     * of the report, and `DependencyNotFoundException` spans five and ends in a
     * URL. The words are what the reader needs, not the newlines between them.
     */
    private function reasonFor(?PillarResolutionFailure $failure): string
    {
        if (!$failure instanceof PillarResolutionFailure) {
            return '';
        }

        return sprintf(': %s: %s', $failure->exceptionClass, trim((string)preg_replace('/\s+/', ' ', $failure->message)));
    }

    /**
     * Whether the class the file should declare is there, which is the only
     * thing that separates a namespace that disagrees with its directory from a
     * class that loads perfectly and then fails to build.
     */
    private function classLoads(AppModule $module, string $file): bool
    {
        $className = $module->fullModuleName() . '\\' . basename($file, '.php');

        try {
            return class_exists($className);
        } catch (Throwable) {
            // Asking is asking PHP to load it, and a file it could not compile
            // the first time throws again here. The reason is already on the
            // detail line; all this decides is which remediation to print, and
            // for a file PHP cannot load the namespace tip is the right one.
            return false;
        }
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
