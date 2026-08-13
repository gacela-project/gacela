<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture;

use Gacela\Framework\Gacela;
use GacelaTest\Integration\Architecture\StatefulFixture\Greeting\Infrastructure\GreetingCommand;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;
use SplFileInfo;

use function array_diff;
use function array_keys;
use function class_exists;
use function count;
use function implode;
use function interface_exists;
use function ksort;
use function realpath;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function strlen;
use function strrpos;
use function substr;
use function trait_exists;

/**
 * Guards process-global state from the opposite end to {@see ResetCacheCoverageTest}.
 *
 * That test starts from the resets that exist and asks who calls them. By
 * construction it cannot see a class that holds static state and declares no
 * reset at all -- and seven such classes are what #539 measured. This one
 * starts from the state instead, so a new `private static array $cache` is
 * covered the moment it is written, whether or not anybody remembered to give
 * it a reset.
 *
 * The assertion is behavioural, not structural: state is really populated by
 * resolving a real module, `Gacela::resetCache()` is really called, and every
 * static is then required to be back at its declared default. "Reset" here
 * means the value actually returned, not a call site that appears in the
 * source.
 *
 * Anything that legitimately outlives a cache reset goes in {@see OUTLIVES_RESET}
 * with the reason. That list is self-invalidating in both directions --
 * see `test_no_declaration_outlives_its_reason`.
 */
final class StaticStateCoverageTest extends TestCase
{
    private const string SRC = __DIR__ . '/../../../src';

    /**
     * The fixture belongs to this test rather than being borrowed from a
     * feature suite, so nobody can defang the workload by editing a module
     * whose tests still pass. It deliberately covers every distinct way Gacela
     * memoizes: pillar resolution, an attribute-declared provider, docblock
     * service resolution and a #[Cacheable] method.
     */
    private const string WORKLOAD_ROOT = __DIR__ . '/StatefulFixture';

    /**
     * Statics that survive `Gacela::resetCache()` on purpose, each with the
     * reason its lifetime is not cache lifetime.
     *
     * Two distinct reasons appear here, and they are not interchangeable:
     *
     * - **configuration** — established by the application or by bootstrap.
     *   Clearing it on a cache reset would silently drop what the app asked
     *   for, which is a worse bug than the staleness it would prevent.
     * - **pure memoization** — a total function of a key that cannot change
     *   within a process (a class name, a loaded class's reflection). Nothing
     *   can go stale, and the size is bounded by the loaded classes, so there
     *   is nothing for a reset to fix.
     *
     * A cache of anything *resolved* -- instances, containers, config values,
     * file contents keyed by path -- belongs in neither category and must be
     * cleared centrally.
     *
     * @var array<string,string>
     */
    private const array OUTLIVES_RESET = [
        'Gacela::$appRootDir' => 'configuration: the bootstrapped app root. resetCache() clears caches, it does not un-bootstrap Gacela',
        'Gacela::$mainContainer' => "configuration: built by bootstrap from the app's GacelaConfig, and reset() would leave the framework with no container at all",
        'CacheableConfig::$storage' => 'configuration: the backend registered by the app via CacheableConfig::setStorage(). Nothing in the framework re-establishes it, so clearing it would downgrade a configured Redis/APCu store to the in-memory default',
        'CacheableConfig::$ttlOverrides' => 'configuration: per-method TTLs registered by the app alongside the storage above, and re-established by nothing',
        'HealthCheckRegistry::$checks' => 'configuration: checks registered through GacelaConfig::addHealthCheck(). Bootstrap resets it explicitly before reading the config files, so checks accumulate within one bootstrap and never across two',
        'ClassInfo::$callerClassCache' => 'pure memoization: caller class name plus resolvable type to a value object holding a cache key. That key names the kind a suffix belongs to, so the entries are dropped when the declared kinds change -- by whoever changes them, since checking a stamp on every lookup cost more than the lookup itself',
        'GlobalKey::$cache' => 'pure memoization: a class name to its global key string, dropped alongside ClassInfo above and for the same reason',
        'ResolvableTypes::$suffixes' => "configuration: the kinds the assembled gacela.php declared, replaced wholesale by the next bootstrap's sync. Clearing it on a cache reset would drop and restore the same set once per bootstrap, invalidating the two key memos built from it each time",
        'ResolvableTypes::$matchOrder' => 'pure memoization: the suffixes above, ordered longest first. Dropped by the one thing that can invalidate it, which is those suffixes changing',
        'ProvidesScanner::$cache' => 'pure memoization: a provider class to the #[Provides] methods reflected off it. The class cannot gain a method mid-process',
        'ReflectionClassPool::$cache' => 'pure memoization: reflection of a loaded class, which cannot change once loaded',
        'ServiceResolverAwareTrait::$docBlockResolvers' => 'unclearable by construction: a trait static exists once per using class, including application classes the framework never sees, so no central reset could reach them all. Safe only because it is pure memoization -- a caller class to the resolver built from its #[ServiceMap] attribute or docblock, both fixed at compile time',
        'ServiceResolverAwareTrait::$docBlockServiceResolvers' => 'unclearable by construction, as above. Pure memoization of a resolvable type to its resolver; the instances it resolves live in AbstractClassResolver::$cachedInstances, which is cleared',
        'CacheableTrait::$attributeCache' => 'pure memoization: a class method to its #[Cacheable] attribute. The cached return values live in CacheableConfig::getStorage(), not here',
        'CacheableTrait::$cacheMissSentinel' => 'not state: a per-class sentinel object whose only property is its identity, used to tell a cached null from a miss',
        'DocBlockResolver::$fileContentCache' => 'pure memoization: a source file path to its contents. PHP has already compiled those files; a change on disk cannot affect the running process',
        'DocBlockResolver::$warned' => 'deliberately process-wide: it deduplicates the docblock-fallback deprecation, so a reset would make the same notice fire again for a caller already warned about',
        'ClassValidator::$existsCache' => 'partial by design: resetCache() drops the negative answers, because a class that did not exist may now be loadable, and keeps the positive ones, because a loaded class cannot unload',
        'EventDispatcherProvider::$preBootstrapDispatcher' => 'not state: a shared NullEventDispatcher that keeps dispatch sites silent before a resolver exists. It has no fields, so there is nothing in it to go stale. The dispatcher and resolver beside it are cleared',
        'Profiler::$instance' => 'observation lifetime, not cache lifetime: the profiler is opt-in and accumulates measurements across a run on purpose. Clearing it on a cache reset would discard the profile the app asked for, half-way through collecting it',
        'ClassRules::$classAnalysers' => 'pure memoization, and not in a gacela process at all: the architecture rules psalm runs, built once because the handler is called for every class-like it analyses. They hold only their own configuration, and resetCache() belongs to an application runtime this never shares',
        'ClassRules::$facadeMethods' => 'pure memoization, as above: the one rule that judges a method rather than a class',
        'ClassRules::$cacheableKeys' => 'pure memoization, as above: the second rule that judges a method rather than a class, this one reading the #[Cacheable] attribute on it',
        'CrossModuleRules::$analyser' => "configuration: the boundary check psalm was asked to run, read from the consumer's <crossModule> element at plugin registration. Nothing re-establishes it, so clearing it would silently turn the rule off",
        'CrossModuleCallRules::$analyser' => 'configuration: the other half of the same check, registered from the same element',
    ];

    protected function tearDown(): void
    {
        Gacela::resetCache();
    }

    public function test_every_static_is_cleared_by_reset_cache_or_declared_to_outlive_it(): void
    {
        $properties = $this->discover();

        $this->runWorkload();
        Gacela::resetCache();

        $leaked = [];
        foreach ($properties as $key => $property) {
            if (isset(self::OUTLIVES_RESET[$key])) {
                continue;
            }

            if (!$this->isAtDeclaredDefault($property)) {
                $leaked[] = $key;
            }
        }

        self::assertSame([], $leaked, sprintf(
            "These statics still hold state after Gacela::resetCache(): %s.\n"
            . 'Either clear them from Gacela::resetCache(), or add them to OUTLIVES_RESET '
            . 'with the reason their lifetime is not cache lifetime.',
            implode(', ', $leaked),
        ));
    }

    /**
     * Without this, the test above degrades silently: if the workload ever
     * stops resolving anything -- a moved fixture, a swallowed exception --
     * every static stays at its default and the assertion passes while
     * checking nothing.
     */
    public function test_the_workload_really_populates_state(): void
    {
        $properties = $this->discover();

        $this->runWorkload();

        $populated = [];
        foreach ($properties as $key => $property) {
            if (!$this->isAtDeclaredDefault($property)) {
                $populated[] = $key;
            }
        }

        self::assertGreaterThan(10, count($populated), sprintf(
            'Resolving the fixture module populated only %d statics (%s), which is too few for '
            . 'the reset assertion to mean anything. The workload has stopped exercising Gacela.',
            count($populated),
            implode(', ', $populated),
        ));
    }

    /**
     * An entry for a static that no longer exists, or that is cleared centrally
     * after all, is stale -- and a stale entry is how the next leak gets waved
     * through.
     */
    public function test_no_declaration_outlives_its_reason(): void
    {
        $properties = $this->discover();

        $unknown = array_diff(array_keys(self::OUTLIVES_RESET), array_keys($properties));
        self::assertSame([], $unknown, sprintf(
            'Declared to outlive the reset but no longer a static property: %s. Drop the entry.',
            implode(', ', $unknown),
        ));

        $this->runWorkload();

        // Captured while the workload's state is still live: an entry the
        // workload never reaches is inert after the reset either way, and
        // reading it as "cleared" would delete a correct entry.
        $populated = [];
        foreach (array_keys(self::OUTLIVES_RESET) as $key) {
            if (!$this->isAtDeclaredDefault($properties[$key])) {
                $populated[] = $key;
            }
        }

        Gacela::resetCache();

        $nowCleared = [];
        foreach ($populated as $key) {
            if ($this->isAtDeclaredDefault($properties[$key])) {
                $nowCleared[] = $key;
            }
        }

        self::assertSame([], $nowCleared, sprintf(
            'Declared to outlive the reset, but Gacela::resetCache() now clears them: %s. Drop the entry.',
            implode(', ', $nowCleared),
        ));
    }

    private function runWorkload(): void
    {
        Gacela::bootstrap(self::WORKLOAD_ROOT);

        (new GreetingCommand())->run('Gacela');
    }

    private function isAtDeclaredDefault(ReflectionProperty $property): bool
    {
        if (!$property->isInitialized()) {
            return false;
        }

        return $property->getValue() === ($property->hasDefaultValue() ? $property->getDefaultValue() : null);
    }

    /**
     * @return array<string, ReflectionProperty>
     */
    private function discover(): array
    {
        $out = [];

        /** @var iterable<string, SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root()));

        foreach ($files as $path => $file) {
            if (!$file->isFile()) {
                continue;
            }

            if (!str_ends_with((string)$path, '.php')) {
                continue;
            }

            $fqcn = $this->fqcnFor((string)$path);
            if (interface_exists($fqcn)) {
                continue;
            }

            if (trait_exists($fqcn)) {
                continue;
            }

            if (!class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);
            foreach ($reflection->getProperties(ReflectionProperty::IS_STATIC) as $property) {
                $owner = $this->declaringSourceOf($reflection, $property);
                $out[$this->shortName($owner) . '::$' . $property->getName()] = $property;
            }
        }

        ksort($out);

        return $out;
    }

    /**
     * A static declared in a trait exists once per using class, so reflection
     * reports each user as the declaring class. The lifetime decision belongs
     * to the trait that wrote it, not to each of its thirteen console commands.
     */
    private function declaringSourceOf(ReflectionClass $class, ReflectionProperty $property): string
    {
        foreach ($class->getTraits() as $trait) {
            if ($trait->hasProperty($property->getName())) {
                return $trait->getName();
            }
        }

        return $property->getDeclaringClass()->getName();
    }

    private function fqcnFor(string $path): string
    {
        $relative = substr($path, strlen($this->root()) + 1);

        return 'Gacela\\' . str_replace(['/', '.php'], ['\\', ''], $relative);
    }

    private function root(): string
    {
        return (string)realpath(self::SRC);
    }

    private function shortName(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return $position === false ? $fqcn : substr($fqcn, $position + 1);
    }
}
