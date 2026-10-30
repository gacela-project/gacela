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
        self::resetInstances();
        self::$classNameFinder = null;
        self::$container = null;
    }

    /**
     * Drops the resolved pillar instances, while how to find and build them
     * stays known.
     *
     * @internal see {@see \Gacela\Framework\Gacela::resetRequestState()}
     */
    public static function resetInstances(): void
    {
        self::$cachedInstances = [];
        CallerInstanceCache::reset();
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

    /**
     * @param class-string $className
     */
    public static function resolveClassName(string $className, string $kind): object
    {
        return self::resolveExactClass($className, ClassInfo::from($className, $kind));
    }

    /**
     * The class this resolver would build for the caller, without building it.
     *
     * @internal
     *
     * @param object|class-string $caller
     *
     * @return class-string|null
     */
    public function findClassNameFor(object|string $caller): ?string
    {
        $className = $this->findClassName(ClassInfo::from($caller, $this->getResolvableType()));

        return $className === null ? null : ltrim($className, '\\');
    }

    abstract protected function getResolvableType(): string;

    /**
     * @param object|class-string $caller
     */
    protected function doResolve(object|string $caller, ?string $previousCacheKey = null): ?object
    {
        $classInfo = ClassInfo::from($caller, $this->getResolvableType());
        $registrationKey = $previousCacheKey ?? $classInfo->getCacheKey();
        $resolvedClass = $this->resolveCached($registrationKey, $caller);
        if ($resolvedClass !== null) {
            if (self::shouldDispatch(ResolvedClassCachedEvent::class)) {
                self::dispatchEvent(new ResolvedClassCachedEvent($classInfo));
            }

            return $resolvedClass;
        }

        // A resolution listener can invalidate this cache before the result is stored.
        $callerToken = CallerInstanceCache::token();
        $resolvedClassName = $this->findClassName($classInfo);
        if ($resolvedClassName !== null) {
            $instance = self::resolveExactClass($resolvedClassName, $classInfo);
            self::$cachedInstances[$registrationKey] = $instance;
        } else {
            [$instance, $classInfo] = $this->resolveFromParents($caller, $classInfo);
            if ($instance === null) {
                if (self::shouldDispatch(ResolvedCreatedDefaultClassEvent::class)) {
                    self::dispatchEvent(new ResolvedCreatedDefaultClassEvent($classInfo));
                }

                $instance = $this->createDefaultGacelaClass();
            }

            if (CallerInstanceCache::isCurrent($callerToken)) {
                $this->cacheCallerInstance($registrationKey, $caller, $instance);
            }
        }

        return $instance;
    }

    /**
     * Fallback instance used when no class could be resolved for the caller.
     * Concrete resolvers may override this to provide a default implementation.
     */
    protected function createDefaultGacelaClass(): ?object
    {
        return null;
    }

    /**
     * @param object|class-string $caller
     */
    private function cacheCallerInstance(string $registrationKey, object|string $caller, ?object $instance): void
    {
        $callerClass = ltrim(is_object($caller) ? $caller::class : $caller, '\\');
        CallerInstanceCache::$instances[$registrationKey][$callerClass] = $instance;
        CallerInstanceCache::$instances[$registrationKey]['\\' . $callerClass] = $instance;
    }

    /**
     * @param object|class-string $caller
     *
     * @return array{?object, ClassInfo}
     */
    private function resolveFromParents(object|string $caller, ClassInfo $classInfo): array
    {
        $visited = [$classInfo->getCacheKey() => true];
        foreach ($this->parentClasses($caller) as $parentClass) {
            $parentInfo = ClassInfo::from($parentClass, $this->getResolvableType());
            if (isset($visited[$parentInfo->getCacheKey()])) {
                continue;
            }

            $visited[$parentInfo->getCacheKey()] = true;
            $this->dispatchParentRetry($classInfo);
            $classInfo = $parentInfo;
            $instance = $this->resolveParentInfo($parentInfo);
            if ($instance !== null) {
                return [$instance, $classInfo];
            }
        }

        return [null, $classInfo];
    }

    /**
     * @param object|class-string $caller
     *
     * @return iterable<class-string>
     */
    private function parentClasses(object|string $caller): iterable
    {
        if (!is_object($caller) && !class_exists($caller)) {
            return;
        }

        while (($caller = get_parent_class($caller)) !== false) {
            yield $caller;
        }
    }

    private function resolveParentInfo(ClassInfo $classInfo): ?object
    {
        $global = AnonymousGlobal::getByKey($classInfo->getCacheKey());
        if ($global !== null) {
            self::dispatchResolvedClassEvent($classInfo, true);

            return $global;
        }

        $className = $this->findClassName($classInfo);

        return $className === null ? null : self::resolveExactClass($className, $classInfo);
    }

    private function dispatchParentRetry(ClassInfo $classInfo): void
    {
        if (self::shouldDispatch(ResolvedClassTriedFromParentEvent::class)) {
            self::dispatchEvent(new ResolvedClassTriedFromParentEvent($classInfo));
        }
    }

    /**
     * @param class-string $className
     */
    private static function resolveExactClass(string $className, ClassInfo $classInfo): object
    {
        $className = ltrim($className, '\\');
        $cacheKey = 'class:' . $className;
        $resolved = AnonymousGlobal::getByClassName($className) ?? self::$cachedInstances[$cacheKey] ?? null;
        if ($resolved !== null) {
            self::dispatchResolvedClassEvent($classInfo, true);

            return $resolved;
        }

        /** @var object $instance */
        $instance = self::pillarContainer()->get($className);
        self::dispatchResolvedClassEvent($classInfo, false);

        return self::$cachedInstances[$cacheKey] = $instance;
    }

    private static function dispatchResolvedClassEvent(ClassInfo $classInfo, bool $cached): void
    {
        $eventClass = $cached ? ResolvedClassCachedEvent::class : ResolvedClassCreatedEvent::class;
        if (self::shouldDispatch($eventClass)) {
            self::dispatchEvent(new $eventClass($classInfo));
        }
    }

    /**
     * @param object|class-string $caller
     */
    private function resolveCached(string $registrationKey, object|string $caller): ?object
    {
        return AnonymousGlobal::getByKey($registrationKey)
            ?? self::$cachedInstances[$registrationKey]
            ?? CallerInstanceCache::$instances[$registrationKey][is_object($caller) ? $caller::class : $caller]
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
     * Asked of the ConfigFactory every time rather than memoized here.
     *
     * A resolver instance can outlive the bootstrap that built it -- the ones
     * held by `ServiceResolverAwareTrait::$docBlockServiceResolvers` are a trait
     * static, which no reset can reach -- and a memo on this object was then a
     * copy of the *previous* application's merged configuration, from which
     * it built the shared container. A second bootstrap in one
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
