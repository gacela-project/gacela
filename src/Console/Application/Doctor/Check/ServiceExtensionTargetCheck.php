<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Doctor\Check;

use Gacela\Console\Application\Doctor\CheckResult;
use Gacela\Console\Application\Doctor\HealthCheck;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Throwable;

use function array_flip;
use function count;
use function is_subclass_of;
use function sprintf;

/**
 * Every `extendService()` id, checked against what some Provider actually
 * `set()`s.
 *
 * An extension on an id nothing ever stores is accepted, scheduled on every
 * scope, and applied nowhere -- no exception, no event (#683). No runtime
 * moment can prove the miss, because module scopes build lazily and an
 * optional module may provide the id only in some deployments; a diagnostic
 * command can afford what the runtime cannot: running every discovered
 * Provider once, eagerly, into a throwaway scope.
 *
 * The union is `getRegisteredServices()` on purpose, not the broader
 * `provides()`: the extension queue drains only through `set()`, so an id
 * registered via `bind()`/`singleton()` is a real registration whose
 * extension still never applies -- exactly what this check exists to say.
 *
 * An id both registered at application level and `set()` by a Provider is
 * the other silent miss: a module scope skips extensions on ids the
 * application container provides, so the Provider's value is never extended.
 */
final class ServiceExtensionTargetCheck implements HealthCheck
{
    /**
     * @param list<AppModule> $modules
     * @param list<string> $extensionIds ids passed to extendService()
     * @param list<string> $appContainerServiceIds ids the app container itself stores
     * @param array<class-string, list<string>> $providerScopedIds ids passed to extendProviderService(), by provider
     */
    public function __construct(
        private readonly array $modules,
        private readonly array $extensionIds,
        private readonly array $appContainerServiceIds,
        private readonly array $providerScopedIds = [],
    ) {
    }

    public function name(): string
    {
        return 'service extensions';
    }

    public function run(): CheckResult
    {
        if ($this->extensionIds === [] && $this->providerScopedIds === []) {
            return CheckResult::ok($this->name(), 'no service extensions registered');
        }

        $warnings = [];
        $appLevel = array_flip($this->appContainerServiceIds);
        $provided = $appLevel;
        $extended = array_flip($this->extensionIds);

        foreach ($this->modules as $module) {
            $providerClass = $module->providerClass();
            if ($providerClass === null) {
                continue;
            }

            foreach ($this->registeredBy($providerClass, $warnings) as $id) {
                if (isset($extended[$id], $appLevel[$id])) {
                    $warnings[] = sprintf(
                        "'%s' is extended and registered at application level, so %s's own registration is never extended -- that module gets the unextended service",
                        $id,
                        $providerClass,
                    );
                }

                $provided[$id] = true;
            }
        }

        foreach ($this->extensionIds as $extensionId) {
            if (!isset($provided[$extensionId])) {
                $warnings[] = sprintf(
                    "'%s' is extended, and no Provider set()s it -- the extension will never apply",
                    $extensionId,
                );
            }
        }

        $scopedCount = 0;
        foreach ($this->providerScopedIds as $providerClass => $ids) {
            $registered = array_flip($this->registeredBy($providerClass, $warnings));

            foreach ($ids as $id) {
                ++$scopedCount;

                if (isset($registered[$id])) {
                    continue;
                }

                $warnings[] = sprintf(
                    "'%s' is extended on %s, which never set()s it -- the extension will never apply",
                    $id,
                    $providerClass,
                );
            }
        }

        if ($warnings !== []) {
            return CheckResult::warn(
                $this->name(),
                $warnings,
                "check the id for a typo; an extension only applies once some Provider set()s that id -- bind() and singleton() do not drain it; an id registered at application level is extended there only, so drop that registration or the Provider's",
            );
        }

        return CheckResult::ok(
            $this->name(),
            sprintf('%d extension id(s) matched', count($this->extensionIds) + $scopedCount),
        );
    }

    /**
     * What one Provider stores, read off a throwaway scope. A Provider that
     * cannot run outside its deployment is reported instead of crashing the
     * diagnosis of every other one.
     *
     * Named directly by `extendProviderService()`, so the miss is sharper than
     * the app-wide one: not "nobody set this" but "the Provider you named does
     * not". A slot holding a class that is not a Provider is skipped rather
     * than instantiated and run.
     *
     * @param list<string> $warnings
     *
     * @return list<string>
     */
    private function registeredBy(string $providerClass, array &$warnings): array
    {
        if (!is_subclass_of($providerClass, AbstractProvider::class)) {
            return [];
        }

        try {
            $provider = new $providerClass();

            $container = new Container();
            $provider->register($container);

            return $container->getRegisteredServices();
        } catch (Throwable $throwable) {
            $warnings[] = sprintf('%s failed to run: %s', $providerClass, $throwable->getMessage());

            return [];
        }
    }
}
