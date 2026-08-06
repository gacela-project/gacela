<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Closure;
use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\AppModule;
use ReflectionClass;

use function count;
use function explode;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Reports pillar classes whose file is named differently from the class.
 *
 * Gacela resolves pillars by *filename* suffix, so a class renamed without its
 * file stops resolving with nothing pointing at the cause. That is the step
 * people miss migrating `AbstractDependencyProvider` -> `AbstractProvider`:
 * `DependencyProvider.php` has to become `Provider.php` too.
 */
final class FilenameMismatchCheck implements HealthCheck
{
    private const PILLAR_SUFFIXES = ['Facade', 'Factory', 'Config', 'Provider'];

    /** @var Closure(class-string):?string */
    private readonly Closure $fileResolver;

    /** @var Closure(string):list<string> */
    private readonly Closure $directoryScanner;

    /** @var Closure(string):?string */
    private readonly Closure $declaredClassReader;

    /**
     * @param list<AppModule> $modules
     * @param null|Closure(class-string):?string $fileResolver resolves a class-name to its source file path
     * @param null|Closure(string):list<string> $directoryScanner lists the php files in a directory
     * @param null|Closure(string):?string $declaredClassReader reads the short class name a file declares
     */
    public function __construct(
        private readonly array $modules,
        ?Closure $fileResolver = null,
        ?Closure $directoryScanner = null,
        ?Closure $declaredClassReader = null,
    ) {
        $this->fileResolver = $fileResolver ?? static function (string $className): ?string {
            if (!class_exists($className)) {
                return null;
            }

            $file = (new ReflectionClass($className))->getFileName();

            return $file === false ? null : $file;
        };

        $this->directoryScanner = $directoryScanner ?? static function (string $directory): array {
            $files = glob($directory . '/*.php');

            return $files === false ? [] : $files;
        };

        $this->declaredClassReader = $declaredClassReader ?? $this->readDeclaredClass(...);
    }

    public function name(): string
    {
        return 'pillar filenames';
    }

    public function run(): CheckResult
    {
        $mismatches = [];

        foreach ($this->modules as $module) {
            foreach ($this->pillarsOf($module) as $pillarClass) {
                $mismatch = $this->inspect($pillarClass);
                if ($mismatch !== null) {
                    $mismatches[] = $mismatch;
                }
            }

            foreach ($this->inspectDirectoryOf($module) as $mismatch) {
                $mismatches[] = $mismatch;
            }
        }

        // The two passes overlap under classmap autoloading, where a mismatched
        // class both resolves and is on disk.
        $mismatches = array_values(array_unique($mismatches));

        if ($mismatches === []) {
            return CheckResult::ok($this->name(), 'every pillar class matches its filename');
        }

        return CheckResult::error(
            $this->name(),
            $mismatches,
            'rename the file to match the class — pillars resolve by filename, '
            . 'so `Provider` declared in `DependencyProvider.php` is never found',
        );
    }

    /**
     * The short name of the class a file declares, without loading it — loading
     * is the one thing that cannot work here, since a mismatched file does not
     * autoload.
     */
    private function readDeclaredClass(string $file): ?string
    {
        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        $tokens = token_get_all($contents);

        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] !== T_CLASS) {
                continue;
            }

            // `Foo::class` is a T_CLASS too, and an anonymous class has no name.
            if ($index > 0 && $this->isDoubleColon($tokens[$index - 1])) {
                continue;
            }

            $name = $this->nextClassName($tokens, $index);
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param array{0:int,1:string,2:int}|string $token
     */
    private function isDoubleColon(array|string $token): bool
    {
        return is_array($token) && $token[0] === T_DOUBLE_COLON;
    }

    /**
     * @param list<array{0:int,1:string,2:int}|string> $tokens
     */
    private function nextClassName(array $tokens, int $index): ?string
    {
        for ($next = $index + 1, $total = count($tokens); $next < $total; ++$next) {
            $token = $tokens[$next];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($token) && $token[0] === T_STRING ? $token[1] : null;
        }

        return null;
    }

    /**
     * @return array<class-string>
     */
    private function pillarsOf(AppModule $module): array
    {
        return array_filter(
            [
                $module->facadeClass(),
                $module->factoryClass(),
                $module->configClass(),
                $module->providerClass(),
            ],
            static fn (?string $pillar): bool => $pillar !== null,
        );
    }

    /**
     * The pass that sees the case this check exists for.
     *
     * A pillar whose file does not match its class cannot autoload under PSR-4,
     * so it resolves to null, `pillarsOf()` drops it, and the pass above reports
     * nothing on exactly the module whose provider silently never runs. Reading
     * the directory instead finds it precisely when the class does not resolve.
     *
     * The module directory is taken from the facade, which is what made the
     * module discoverable in the first place.
     *
     * @return list<string>
     */
    private function inspectDirectoryOf(AppModule $module): array
    {
        $facadeFile = ($this->fileResolver)($module->facadeClass());
        if ($facadeFile === null) {
            return [];
        }

        $mismatches = [];

        foreach (($this->directoryScanner)($this->directoryOf($facadeFile)) as $file) {
            $filename = $this->basename($file);
            $declaredClass = ($this->declaredClassReader)($file);
            if ($declaredClass === null) {
                continue;
            }

            if ($declaredClass === $filename) {
                continue;
            }

            if (!$this->carriesPillarSuffix($filename) && !$this->carriesPillarSuffix($declaredClass)) {
                continue;
            }

            $mismatches[] = sprintf(
                '%s\\%s is declared in %s.php, expected %s.php',
                $module->fullModuleName(),
                $declaredClass,
                $filename,
                $declaredClass,
            );
        }

        return $mismatches;
    }

    private function carriesPillarSuffix(string $name): bool
    {
        foreach (self::PILLAR_SUFFIXES as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function directoryOf(string $file): string
    {
        $parts = $this->pathSegments($file);
        array_pop($parts);

        return implode('/', $parts);
    }

    /**
     * Splits on both separators, so a Windows path is handled the same as a
     * POSIX one — `getFileName()` reports whichever the platform uses.
     *
     * @return non-empty-list<string>
     */
    private function pathSegments(string $file): array
    {
        return explode('/', str_replace('\\', '/', $file));
    }

    /**
     * @param class-string $pillarClass
     */
    private function inspect(string $pillarClass): ?string
    {
        $file = ($this->fileResolver)($pillarClass);
        if ($file === null) {
            return null;
        }

        $expected = $this->shortName($pillarClass);
        $actual = $this->basename($file);

        if ($actual === $expected) {
            return null;
        }

        return sprintf('%s is declared in %s.php, expected %s.php', $pillarClass, $actual, $expected);
    }

    private function shortName(string $className): string
    {
        $parts = explode('\\', $className);

        return $parts[count($parts) - 1];
    }

    private function basename(string $file): string
    {
        $parts = $this->pathSegments($file);
        $filename = $parts[count($parts) - 1];

        return str_ends_with($filename, '.php')
            ? substr($filename, 0, -4)
            : $filename;
    }
}
