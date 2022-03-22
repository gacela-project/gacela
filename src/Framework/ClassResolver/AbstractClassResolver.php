<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use Gacela\Framework\ClassResolver\Cache\FakeFileCached;
use Gacela\Framework\ClassResolver\Cache\FileCached;
use Gacela\Framework\ClassResolver\Cache\FileCachedInterface;
use Gacela\Framework\ClassResolver\Cache\FileCachedIo;
use Gacela\Framework\ClassResolver\Cache\FileCachedIoInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinderInterface;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\ClassResolver\InstanceCreator\InstanceCreator;
use Gacela\Framework\Config;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Shared\FileIo;
use Gacela\Framework\Shared\FileIoInterface;
use function is_array;

abstract class AbstractClassResolver
{
    public const GACELA_RESOLVABLE_CACHE_FILE = 'gacela-resolvable-cache.php';

    /** @var array<string,null|object> */
    private static array $cachedInstances = [];

    private ?FileCachedInterface $fileCached = null;

    private ?ClassNameFinderInterface $classNameFinder = null;

    private ?GacelaConfigFileInterface $gacelaFileConfig = null;

    private ?InstanceCreator $instanceCreator = null;

    abstract public function resolve(object $callerClass): ?object;

    public static function cleanCache(): void
    {
        self::$cachedInstances = [];
    }

    public function doResolve(object $callerClass): ?object
    {
        $classInfo = ClassInfo::fromObject($callerClass, $this->getResolvableType());
        $cacheKey = $classInfo->getCacheKey();

        $resolvedClass = $this->resolveCached($cacheKey);
        if (null !== $resolvedClass) {
            return $resolvedClass;
        }

        $resolvedClassName = $this->findClassName($classInfo);
        if (null === $resolvedClassName) {
            return null;
        }

        self::$cachedInstances[$cacheKey] = $this->createInstance($resolvedClassName);

        return self::$cachedInstances[$cacheKey];
    }

    abstract protected function getResolvableType(): string;

    private function resolveCached(string $cacheKey): ?object
    {
        return AnonymousGlobal::getByKey($cacheKey)
            ?? self::$cachedInstances[$cacheKey]
            ?? null;
    }

    private function findClassName(ClassInfo $classInfo): ?string
    {
        $cachedClassName = $this->getFileCached()->getCachedClassName($classInfo);
        if (null !== $cachedClassName) {
            return $cachedClassName;
        }

        $className = $this->getClassNameFinder()->findClassName(
            $classInfo,
            $this->getPossibleResolvableTypes()
        );

        $this->getFileCached()->cacheClassName($classInfo, $className);

        return $className;
    }

    private function getFileCached(): FileCachedInterface
    {
        if (null === $this->fileCached) {
            if ($this->getGacelaConfigFile()->isResolvableClassNamesCacheEnabled()) {
                $this->fileCached = new FileCached(
                    sprintf('/%s/%s', Config::getInstance()->getAppRootDir(), self::GACELA_RESOLVABLE_CACHE_FILE),
                    $this->createFileCachedIo()
                );
            } else {
                $this->fileCached = new FakeFileCached();
            }
        }

        return $this->fileCached;
    }

    private function createFileCachedIo(): FileCachedIoInterface
    {
        return new FileCachedIo(
            $this->createFileIo()
        );
    }

    private function createFileIo(): FileIoInterface
    {
        return new FileIo();
    }

    private function getClassNameFinder(): ClassNameFinderInterface
    {
        if (null === $this->classNameFinder) {
            $this->classNameFinder = (new ClassResolverFactory())
                ->createClassNameFinder();
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

    private function createInstance(string $resolvedClassName): ?object
    {
        if (null === $this->instanceCreator) {
            $this->instanceCreator = new InstanceCreator($this->getGacelaConfigFile());
        }

        return $this->instanceCreator->createByClassName($resolvedClassName);
    }

    private function getGacelaConfigFile(): GacelaConfigFileInterface
    {
        if (null === $this->gacelaFileConfig) {
            $this->gacelaFileConfig = Config::getInstance()
                ->getFactory()
                ->createGacelaConfigFileFactory()
                ->createGacelaFileConfig();
        }

        return $this->gacelaFileConfig;
    }
}
