<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\ClassResolver\InstanceCreator\InstanceCreator;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\EventListener\Event\GacelaEventInterface;
use Gacela\Framework\EventListener\Event\ResolvedClassCachedEvent;
use Gacela\Framework\EventListener\Event\ResolvedClassCreatedEvent;
use Gacela\Framework\EventListener\Event\ResolvedClassTryFormParentEvent;
use Gacela\Framework\EventListener\Event\ResolvedDefaultClassEvent;
use Gacela\Framework\EventListener\GacelaClassResolverListener;

use function is_array;
use function is_object;

abstract class AbstractClassResolver
{
    /** @var array<string,null|object> */
    private static array $cachedInstances = [];

    /** @var array<string,list<callable>>*/
    private static array $listeners = [];

    private ?ClassNameFinderInterface $classNameFinder = null;

    private ?GacelaConfigFileInterface $gacelaFileConfig = null;

    private ?InstanceCreator $instanceCreator = null;

    /**
     * @internal remove all cached instances: facade, factory, config, dependency-provider
     */
    public static function resetCache(): void
    {
        self::$cachedInstances = [];
        self::$listeners = [];
    }

    /**
     * @param object|class-string $caller
     */
    abstract public function resolve($caller): ?object;

    /**
     * @param object|class-string $caller
     */
    public function doResolve($caller): ?object
    {
        $classInfo = ClassInfo::from($caller, $this->getResolvableType());

        $cacheKey = $classInfo->getCacheKey();

        $resolvedClass = $this->resolveCached($cacheKey);
        if ($resolvedClass !== null) {
            $this->triggerEvent(new ResolvedClassCachedEvent($classInfo));

            return $resolvedClass;
        }

        $resolvedClassName = $this->findClassName($classInfo);
        if ($resolvedClassName !== null) {
            $instance = $this->createInstance($resolvedClassName);
            $this->triggerEvent(new ResolvedClassCreatedEvent($classInfo));
        } else {
            // Try again with its parent class
            if (is_object($caller)) {
                $parentClass = get_parent_class($caller);
                if ($parentClass !== false) {
                    $this->triggerEvent(new ResolvedClassTryFormParentEvent($classInfo));

                    return $this->doResolve($parentClass);
                }
            }

            $this->triggerEvent(new ResolvedDefaultClassEvent($classInfo));
            $instance = $this->createDefaultGacelaClass();
        }

        self::$cachedInstances[$cacheKey] = $instance;

        return self::$cachedInstances[$cacheKey];
    }

    abstract protected function getResolvableType(): string;

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
            $this->getPossibleResolvableTypes()
        );
    }

    private function getClassNameFinder(): ClassNameFinderInterface
    {
        if ($this->classNameFinder === null) {
            $this->classNameFinder = (new ClassResolverFactory(
                new GacelaFileCache(Config::getInstance()),
                Config::getInstance()->getSetupGacela()
            ))->createClassNameFinder();
        }

        return $this->classNameFinder;
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
    private function createInstance(string $resolvedClassName): ?object
    {
        if ($this->instanceCreator === null) {
            $this->instanceCreator = new InstanceCreator($this->getGacelaConfigFile());
        }

        return $this->instanceCreator->createByClassName($resolvedClassName);
    }

    private function getGacelaConfigFile(): GacelaConfigFileInterface
    {
        if ($this->gacelaFileConfig === null) {
            $this->gacelaFileConfig = Config::getInstance()
                ->getFactory()
                ->createGacelaFileConfig();
        }

        return $this->gacelaFileConfig;
    }

    private function createDefaultGacelaClass(): ?object
    {
        switch ($this->getResolvableType()) {
            case FactoryResolver::TYPE:
                return new class() extends AbstractFactory {};
            case ConfigResolver::TYPE:
                return new class() extends AbstractConfig {};
            default:
                return null;
        }
    }

    private function triggerEvent(GacelaEventInterface $event): void
    {
        if (self::$listeners === []) {
            self::$listeners = Config::getInstance()->getSetupGacela()->getListeners();
        }

        foreach (self::$listeners[GacelaClassResolverListener::class] ?? [] as $callable) {
            $callable($event);
        }
    }
}
