<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Container\Container;
use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\Config\ConfigResolver;
use Gacela\Framework\ClassResolver\Facade\FacadeResolver;
use Gacela\Framework\ClassResolver\Factory\FactoryResolver;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassTriedFromParentEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedCreatedDefaultClassEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;

use function is_array;
use function is_object;

abstract class AbstractClassResolver
{
    use EventDispatchingCapabilities;

    /** @var array<string, null|object> */
    private static array $cachedInstances = [];

    private static ?ClassNameFinderInterface $classNameFinder = null;

    private ?GacelaConfigFileInterface $gacelaFileConfig = null;

    private ?Container $container = null;

    /**
     * @internal remove all cached instances: facade, factory, config, dependency-provider
     */
    public static function resetCache(): void
    {
        self::$cachedInstances = [];
        self::$classNameFinder = null;
    }

    /**
     * @param object|class-string $caller
     */
    abstract public function resolve(object|string $caller): ?object;

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
            self::dispatchEvent(new ResolvedClassCachedEvent($classInfo));

            return $resolvedClass;
        }

        $resolvedClassName = $this->findClassName($classInfo);
        if ($resolvedClassName !== null) {
            $instance = $this->createInstance($resolvedClassName);
            self::dispatchEvent(new ResolvedClassCreatedEvent($classInfo));
        } else {
            // Try again with its parent class
            if (is_object($caller)) {
                $parentClass = get_parent_class($caller);
                if ($parentClass !== false) {
                    self::dispatchEvent(new ResolvedClassTriedFromParentEvent($classInfo));

                    return $this->doResolve($parentClass, $cacheKey);
                }
            }

            self::dispatchEvent(new ResolvedCreatedDefaultClassEvent($classInfo));
            $instance = $this->createDefaultGacelaClass();
        }

        self::$cachedInstances[$cacheKey] = $instance;

        return self::$cachedInstances[$cacheKey];
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
        if (!$this->container instanceof Container) {
            $this->container = new Container(
                $this->getGacelaConfigFile()->getBindings(),
            );
        }

        /** @var object $instance */
        $instance = $this->container->get($resolvedClassName);

        return $instance;
    }

    private function getGacelaConfigFile(): GacelaConfigFileInterface
    {
        if (!$this->gacelaFileConfig instanceof GacelaConfigFileInterface) {
            $this->gacelaFileConfig = Config::getInstance()
                ->getFactory()
                ->createGacelaFileConfig();
        }

        return $this->gacelaFileConfig;
    }

    private function createDefaultGacelaClass(): ?object
    {
        return match ($this->getResolvableType()) {
            FacadeResolver::TYPE => new class() extends AbstractFacade {},
            FactoryResolver::TYPE => new class() extends AbstractFactory {},
            ConfigResolver::TYPE => new class() extends AbstractConfig {},
            default => null,
        };
    }
}
