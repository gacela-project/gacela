<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Attribute\Provides;
use ReflectionClass;
use ReflectionMethod;

use function class_exists;
use function count;
use function sprintf;

/**
 * A `#[Provides]` attribute the scanner cannot see.
 *
 * `ProvidesScanner::entriesFor()` reads `getMethods(ReflectionMethod::IS_PUBLIC)`,
 * so the attribute on a private or protected method is simply not there as far
 * as the container is concerned. PHP accepts it, the method reads as a declared
 * service, and `getProvidedDependency()` for that id answers `null` -- which is
 * itself silent, so the first sign is a call on null somewhere else entirely.
 *
 * Asked of reflection directly rather than through the scanner, which by
 * construction cannot report what it filters out.
 */
final class UnreachableProvidesCheck implements HealthCheck
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
        return 'unreachable #[Provides]';
    }

    public function run(): CheckResult
    {
        $warnings = [];

        foreach ($this->modules as $module) {
            foreach ($this->unreachableIn($module) as $warning) {
                $warnings[] = $warning;
            }
        }

        if ($warnings !== []) {
            return CheckResult::warn(
                $this->name(),
                $warnings,
                'make the method public -- the scanner reads public methods only, so anything else declares nothing',
            );
        }

        return CheckResult::ok($this->name(), sprintf('%d provider(s) checked', count($this->modules)));
    }

    /**
     * @return list<string>
     */
    private function unreachableIn(AppModule $module): array
    {
        $providerClass = $module->providerClass();
        if ($providerClass === null) {
            return [];
        }

        if (!class_exists($providerClass)) {
            return [];
        }

        $warnings = [];

        foreach ((new ReflectionClass($providerClass))->getMethods() as $method) {
            if ($method->isPublic()) {
                continue;
            }

            foreach ($method->getAttributes(Provides::class) as $attribute) {
                /** @var Provides $provides */
                $provides = $attribute->newInstance();

                $warnings[] = sprintf(
                    "'%s' is declared on %s::%s(), which is %s -- the scanner reads public methods only, so nothing is registered",
                    $provides->id,
                    $providerClass,
                    $method->getName(),
                    $this->visibilityOf($method),
                );
            }
        }

        return $warnings;
    }

    private function visibilityOf(ReflectionMethod $method): string
    {
        return $method->isPrivate() ? 'private' : 'protected';
    }
}
