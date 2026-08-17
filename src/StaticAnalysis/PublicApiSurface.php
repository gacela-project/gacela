<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis;

use Gacela\Framework\Attribute\PublicApi;
use ReflectionClass;
use Throwable;

/**
 * What a module publishes, read the same way for every host.
 *
 * Reading `#[PublicApi]` needs the analysed class, and each host offers its own
 * way to get at it -- PHPStan `ClassReflection::getAttributes()`, Psalm
 * `classlike_storage_provider`. Neither is used: plain `ReflectionClass` here
 * means the two hosts answer alike **by construction** rather than by two
 * parallel implementations that agree until one of them is changed. Agreeing is
 * the point of the feature, so it must not be something a future edit can undo
 * in one host only.
 *
 * Runtime reflection in a static rule is already the precedent in this layer:
 * `CrossModuleMethodCallAnalyser::isExempt()` calls `is_a($receiver, $ignored,
 * true)`, which autoloads exactly the same way.
 */
final class PublicApiSurface
{
    /**
     * The sub-namespaces a module publishes by convention, unless a project says
     * otherwise. Named once so PHPStan and Psalm cannot drift apart on it.
     *
     * @var list<string>
     */
    public const DEFAULT_SEGMENTS = ['Shared', 'Transfer', 'Dto', 'Event'];

    /**
     * Whether the class itself carries `#[PublicApi]`.
     *
     * The class itself, not its parents or interfaces: publishing a base class
     * would silently publish everything anyone ever extends from it, which is the
     * opposite of a module deciding what it exports.
     *
     * False for anything that cannot be loaded. A rule that crashed on an
     * unresolvable name would fail the run rather than report a boundary, and the
     * analysers already treat "cannot tell" as "no finding" -- so `Throwable`
     * rather than `ReflectionException`, since the failure can also come out of
     * an autoloader rather than out of reflection.
     */
    public static function isDeclaredOn(string $class): bool
    {
        try {
            $reflection = new ReflectionClass($class);
        } catch (Throwable) {
            return false;
        }

        return $reflection->getAttributes(PublicApi::class) !== [];
    }
}
