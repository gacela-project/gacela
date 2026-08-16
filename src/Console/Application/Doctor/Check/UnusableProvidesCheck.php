<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Attribute\ProvidesScanner;
use Gacela\Framework\Container\Container;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use function class_exists;
use function count;
use function in_array;
use function sprintf;

/**
 * A `#[Provides]` declaration that cannot supply what it promises.
 *
 * Three ways to write one, all accepted by PHP and none reported anywhere else:
 *
 *  - **not seen** -- `ProvidesScanner::entriesFor()` reads
 *    `getMethods(ReflectionMethod::IS_PUBLIC)`, so the attribute on a private
 *    or protected method is not there as far as the container is concerned;
 *  - **not callable** -- a method with a required parameter the scanner has no
 *    value for is registered and then invoked without it, so resolving the id
 *    raises `ArgumentCountError` at whatever point something first asks for it.
 *    A `Container` parameter is not one of those: the scanner reads the
 *    signature and passes the container through;
 *  - **nothing to supply** -- a method returning `void` or `never` registers
 *    an id whose value is `null`.
 *
 * The first and third are silent: `getProvidedDependency()` answers `null`, so
 * the first sign is a call on null somewhere else entirely. The second is loud
 * but late, and lands on the consumer rather than on the declaration.
 *
 * Asked of reflection directly rather than through the scanner, which by
 * construction cannot report what it filters out.
 */
final class UnusableProvidesCheck implements HealthCheck
{
    /** A return type that cannot carry a value. */
    private const NOTHING_RETURNED = ['void', 'never'];

    /**
     * @param list<AppModule> $modules
     */
    public function __construct(
        private readonly array $modules,
    ) {
    }

    public function name(): string
    {
        return 'unusable #[Provides]';
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
                'a provided method has to be public, take no required parameters, and return the value it declares',
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
            $fault = $this->faultOf($method);
            if ($fault === null) {
                continue;
            }

            foreach ($method->getAttributes(Provides::class) as $attribute) {
                /** @var Provides $provides */
                $provides = $attribute->newInstance();

                $warnings[] = sprintf(
                    "'%s' is declared on %s::%s(), %s",
                    $provides->id,
                    $providerClass,
                    $method->getName(),
                    $fault,
                );
            }
        }

        return $warnings;
    }

    /**
     * Why this method cannot supply what it declares, or null when it can.
     *
     * Only the first fault is named. A private method that also takes an
     * argument has one thing wrong with it as far as a reader is concerned --
     * it does not work -- and two lines about one declaration read as two
     * declarations.
     */
    private function faultOf(ReflectionMethod $method): ?string
    {
        if (!$method->isPublic()) {
            return sprintf(
                'which is %s -- the scanner reads public methods only, so nothing is registered',
                $this->visibilityOf($method),
            );
        }

        $unsupplied = $this->unsuppliedParameterCount($method);

        if ($unsupplied > 0) {
            return sprintf(
                'which requires %d argument(s) -- the scanner calls it with none, so resolving the id raises ArgumentCountError',
                $unsupplied,
            );
        }

        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), self::NOTHING_RETURNED, true)) {
            return sprintf(
                'which returns %s -- the id is registered and answers null',
                $returnType->getName(),
            );
        }

        return null;
    }

    /**
     * Required parameters the scanner has no value for.
     *
     * A `Container` parameter is not one of them: {@see ProvidesScanner::scan()}
     * reads the signature and passes the container through, which is the
     * documented way for a provided method to reach the locator. Counting it as
     * a fault reported every Provider written the way `#[Provides]`'s own
     * example is -- the check contradicting the feature it checks.
     */
    private function unsuppliedParameterCount(ReflectionMethod $method): int
    {
        $count = 0;

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isOptional()) {
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && $type->getName() === Container::class) {
                continue;
            }

            ++$count;
        }

        return $count;
    }

    private function visibilityOf(ReflectionMethod $method): string
    {
        return $method->isPrivate() ? 'private' : 'protected';
    }
}
