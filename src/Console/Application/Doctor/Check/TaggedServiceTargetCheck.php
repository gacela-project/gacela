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
use function class_exists;
use function interface_exists;
use function is_subclass_of;
use function sprintf;

/**
 * Every id given to `tag()`, checked against something that could answer it.
 *
 * `Container::tagged()` resolves each id in turn, and an id naming nothing
 * comes back as `null` -- so the group a module iterates silently contains a
 * hole, and the failure lands on the consumer as "Call to a member function
 * ... on null", pointing at the loop rather than at the registration.
 *
 * An id is answerable two ways: a Provider `set()`s it, or it names a class the
 * container can construct. Only an id that is neither can never resolve, which
 * is why this cannot be a `class_exists()` check at bootstrap -- a tag may
 * legitimately group plain service ids that no class name backs.
 *
 * The same eager, throwaway-scope trick {@see ServiceExtensionTargetCheck}
 * uses, and for the same reason: no runtime moment can prove the miss, because
 * a tag is only resolved when some module asks for it.
 */
final class TaggedServiceTargetCheck implements HealthCheck
{
    /**
     * @param list<AppModule> $modules
     * @param array<string, list<string>> $tags ids grouped by tag name
     * @param list<string> $appContainerServiceIds ids the app container itself stores
     */
    public function __construct(
        private readonly array $modules,
        private readonly array $tags,
        private readonly array $appContainerServiceIds,
    ) {
    }

    public function name(): string
    {
        return 'tagged services';
    }

    public function run(): CheckResult
    {
        if ($this->tags === []) {
            return CheckResult::ok($this->name(), 'no tagged services registered');
        }

        $warnings = [];
        $provided = array_flip($this->appContainerServiceIds);

        foreach ($this->modules as $module) {
            foreach ($this->providedIds($module, $warnings) as $id) {
                $provided[$id] = true;
            }
        }

        $tagged = 0;
        foreach ($this->tags as $tag => $ids) {
            foreach ($ids as $id) {
                ++$tagged;
                if (isset($provided[$id])) {
                    continue;
                }

                if (class_exists($id)) {
                    continue;
                }

                if (interface_exists($id)) {
                    continue;
                }

                $warnings[] = sprintf(
                    "'%s' is tagged '%s', and nothing answers it -- no Provider registers it and no such class exists",
                    $id,
                    $tag,
                );
            }
        }

        if ($warnings !== []) {
            return CheckResult::warn(
                $this->name(),
                $warnings,
                'check the id for a typo; an id nothing answers resolves to null, and the group carrying it fails wherever it is iterated',
            );
        }

        return CheckResult::ok($this->name(), sprintf('%d tagged id(s) answered', $tagged));
    }

    /**
     * What one module's Provider stores, read off a throwaway scope. A
     * Provider that cannot run outside its deployment is reported instead of
     * crashing the diagnosis of every other one.
     *
     * @param list<string> $warnings
     *
     * @return list<string>
     */
    private function providedIds(AppModule $module, array &$warnings): array
    {
        $providerClass = $module->providerClass();
        if ($providerClass === null || !is_subclass_of($providerClass, AbstractProvider::class)) {
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
