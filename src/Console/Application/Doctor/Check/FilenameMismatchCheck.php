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
    /** @var Closure(class-string):?string */
    private readonly Closure $fileResolver;

    /**
     * @param list<AppModule> $modules
     * @param null|Closure(class-string):?string $fileResolver resolves a class-name to its source file path
     */
    public function __construct(
        private readonly array $modules,
        ?Closure $fileResolver = null,
    ) {
        $this->fileResolver = $fileResolver ?? static function (string $className): ?string {
            if (!class_exists($className)) {
                return null;
            }

            $file = (new ReflectionClass($className))->getFileName();

            return $file === false ? null : $file;
        };
    }

    public function name(): string
    {
        return 'pillar filenames';
    }

    public function run(): CheckResult
    {
        $mismatches = [];

        foreach ($this->modules as $module) {
            foreach (self::pillarsOf($module) as $pillarClass) {
                $mismatch = $this->inspect($pillarClass);
                if ($mismatch !== null) {
                    $mismatches[] = $mismatch;
                }
            }
        }

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
     * @return array<class-string>
     */
    private static function pillarsOf(AppModule $module): array
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
     * @param class-string $pillarClass
     */
    private function inspect(string $pillarClass): ?string
    {
        $file = ($this->fileResolver)($pillarClass);
        if ($file === null) {
            return null;
        }

        $expected = self::shortName($pillarClass);
        $actual = self::basename($file);

        if ($actual === $expected) {
            return null;
        }

        return sprintf('%s is declared in %s.php, expected %s.php', $pillarClass, $actual, $expected);
    }

    private static function shortName(string $className): string
    {
        $parts = explode('\\', $className);

        return $parts[count($parts) - 1];
    }

    private static function basename(string $file): string
    {
        $parts = explode('/', str_replace('\\', '/', $file));
        $filename = $parts[count($parts) - 1];

        return str_ends_with($filename, '.php')
            ? substr($filename, 0, -4)
            : $filename;
    }
}
