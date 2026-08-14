<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Attribute\ProvidesScanner;
use ReflectionClass;

use function class_exists;
use function count;
use function implode;
use function sprintf;

/**
 * One `#[Provides]` id declared twice on the same Provider.
 *
 * `ProvidesScanner::scan()` walks the methods in order and `set()`s each id, so
 * the last one wins and every method before it is dead. Nothing says so: both
 * methods read as live, the container answers with one of them, and the only
 * symptom is a value that is not the one the method you were looking at
 * returns.
 *
 * Reported per Provider rather than across the application, because that is
 * where it is unambiguous. Each module resolves through its own container, so
 * two modules declaring the same id is not a collision -- it is two modules
 * each answering for themselves.
 */
final class DuplicateProvidedIdCheck implements HealthCheck
{
    /**
     * @param list<AppModule> $modules
     */
    public function __construct(
        private readonly array $modules,
    ) {
    }

    public function name(): string
    {
        return 'duplicate provided ids';
    }

    public function run(): CheckResult
    {
        $warnings = [];
        $declared = 0;

        foreach ($this->modules as $module) {
            $providerClass = $module->providerClass();
            if ($providerClass === null) {
                continue;
            }

            if (!class_exists($providerClass)) {
                continue;
            }

            $methodsById = $this->methodsById($providerClass);
            $declared += count($methodsById);

            foreach ($methodsById as $id => $methods) {
                if (count($methods) < 2) {
                    continue;
                }

                $warnings[] = sprintf(
                    "'%s' is declared %d times on %s (%s) -- only the last one answers",
                    $id,
                    count($methods),
                    $providerClass,
                    implode(', ', $methods),
                );
            }
        }

        if ($warnings !== []) {
            return CheckResult::warn(
                $this->name(),
                $warnings,
                'give each method its own id, or delete the ones that no longer answer',
            );
        }

        return CheckResult::ok($this->name(), sprintf('%d declared id(s), none repeated', $declared));
    }

    /**
     * @param class-string $providerClass
     *
     * @return array<string, list<string>>
     */
    private function methodsById(string $providerClass): array
    {
        $methodsById = [];

        foreach (ProvidesScanner::entriesFor(new ReflectionClass($providerClass)) as $entry) {
            $methodsById[$entry['id']][] = $entry['method']->getName() . '()';
        }

        return $methodsById;
    }
}
