<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassTriedFromParentEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedCreatedDefaultClassEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;

use function is_array;
use function is_object;
use function ltrim;

abstract class AbstractClassResolver
{
    use EventDispatchingCapabilities;

    /** @var array<string, null|object> */
    private static array $cachedInstances = [];

    private static ?ClassNameFinderInterface $classNameFinder = null;

    /**
     * Shared across every resolver instance/subclass: the configuration is
     * process-global, so one Container serves all.
     */
    private static ?Container $container = null;

    /**
     * @internal remove all cached instances: facade, factory, config, dependency-provider
     */
    public static function resetCache(): void
    {
        self::$cachedInstances = [];
        self::$classNameFinder = null;
        self::$container = null;
    }

    /**
     * @param object|class-string $caller
     */
    abstract public function resolve(object|string $caller): ?object;

    /**
     * The container the four pillars are constructed from.
     *
     * Exposed so a tool that reports on pillar construction can ask the
     * container that performs it rather than `Gacela::container()`, which is
     * configured from the same declarations but is not the one doing the
     * building -- so any disagreement between the two is invisible to it.
     * {@see \Gacela\Console\Application\Debug\ConstructorInspector}
     *
     * Built on first pillar resolution rather than at bootstrap: a process that
     * resolves no pillar -- a console command reading config, say -- should not
     * pay to assemble it, which is also why {@see \Gacela\Framework\AbstractFactory}
     * defers the application container.
     *
     * @internal
     */
    public static function pillarContainer(): Container
    {
        return self::$container ??= Container::forPillars(Config::getInstance());
    }

    abstract protected function getResolvableType(): string;

    /**
     * @param object|class-string $caller
     */
    protected function doResolve(object|string $caller, ?string $previousCacheKey = null): ?object
    {
        $classInfo = ClassInfo::from($caller, $this->getResolvableType());
        $cacheKey = $previousCacheKey ?? $classInfo->getCacheKey();
        $resolvedClass = $this->resolveCached($cacheKey);
        if ($resolvedClass !== null) {
            if (self::shouldDispatch(ResolvedClassCachedEvent::class)) {
                self::dispatchEvent(new ResolvedClassCachedEvent($classInfo));
            }

            return $resolvedClass;
        }

        $resolvedClassName = $this->findClassName($classInfo);
        if ($resolvedClassName !== null) {
            $instance = $this->createInstance($resolvedClassName);
            if (self::shouldDispatch(ResolvedClassCreatedEvent::class)) {
                self::dispatchEvent(new ResolvedClassCreatedEvent($classInfo));
            }
        } else {
            // Try again with its parent class
            if (is_object($caller)) {
                $parentClass = get_parent_class($caller);
                if ($parentClass !== false) {
                    if (self::shouldDispatch(ResolvedClassTriedFromParentEvent::class)) {
                        self::dispatchEvent(new ResolvedClassTriedFromParentEvent($classInfo));
                    }

                    return $this->doResolve($parentClass, $cacheKey);
                }
            }

            if (self::shouldDispatch(ResolvedCreatedDefaultClassEvent::class)) {
                self::dispatchEvent(new ResolvedCreatedDefaultClassEvent($classInfo));
            }

            $instance = $this->createDefaultGacelaClass();
        }

        self::$cachedInstances[$cacheKey] = $instance;

        return self::$cachedInstances[$cacheKey];
    }

    /**
     * Fallback instance used when no class could be resolved for the caller.
     * Concrete resolvers may override this to provide a default implementation.
     */
    protected function createDefaultGacelaClass(): ?object
    {
        return null;
    }

    private function resolveCached(string $cacheKey): ?object
    {
        return AnonymousGlobal::getByKey($cacheKey)
            ?? self::$cachedInstances[$cacheKey]
            ?? null;
    }

    /**
     * @return class-string|null
     */
    private function findClassName(ClassInfo $classInfo): ?string
    {
        return $this->getClassNameFinder()->findClassName(
            $classInfo,
            $this->getPossibleResolvableTypes(),
        );
    }

    private function getClassNameFinder(): ClassNameFinderInterface
    {
        if (!self::$classNameFinder instanceof ClassNameFinderInterface) {
            self::$classNameFinder = (new ClassResolverFactory(
                Config::getInstance()->getSetupGacela(),
            ))->createClassNameFinder();
        }

        return self::$classNameFinder;
    }

    /**
     * Allow overriding gacela suffixes resolvable types.
     *
     * @return list<string>
     */
    private function getPossibleResolvableTypes(): array
    {
        $suffixTypes = $this->getGacelaConfigFile()->getSuffixTypes();

        $resolvableTypes = $suffixTypes[$this->getResolvableType()] ?? $this->getResolvableType();

        return is_array($resolvableTypes) ? $resolvableTypes : [$resolvableTypes];
    }

    /**
     * @param class-string $resolvedClassName
     */
    private function createInstance(string $resolvedClassName): object
    {
        // The finder yields `\Fq\Class\Name` while contextual bindings are keyed
        // by `Fq\Class\Name::class`; normalize so the container can match them.
        /** @var object $instance */
        $instance = self::pillarContainer()->get(ltrim($resolvedClassName, '\\'));

        return $instance;
    }

    /**
     * Asked of the ConfigFactory every time rather than memoized here.
     *
     * A resolver instance can outlive the bootstrap that built it -- the ones
     * held by `ServiceResolverAwareTrait::$docBlockServiceResolvers` are a trait
     * static, which no reset can reach -- and a memo on this object was then a
     * copy of the *previous* application's merged configuration, from which
     * `createInstance()` built the shared container. A second bootstrap in one
     * process therefore resolved its pillars against the first application's
     * bindings: silently, and only for whoever had used a `#[ServiceMap]`
     * accessor before.
     *
     * Nothing is re-derived by removing it. `ConfigFactory::createGacelaFileConfig()`
     * memoizes the assembled file config itself, keyed by the app root and the
     * setup identity -- which is exactly the pair that changes across a
     * bootstrap, and exactly what this memo could not see. This is also the
     * cold path: the callers are the class-name finder on a cache miss, and the
     * one-time container build.
     */
    private function getGacelaConfigFile(): GacelaConfigFileInterface
    {
        return Config::getInstance()
            ->getFactory()
            ->createGacelaFileConfig();
    }
}
