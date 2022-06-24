<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Cache\GacelaCache;
use Gacela\Framework\ClassResolver\ClassNameCacheInterface;
use Gacela\Framework\ClassResolver\DocBlockService\CustomServicesCache;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockParser;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\ClassResolver\DocBlockService\MissingClassDefinitionException;
use Gacela\Framework\ClassResolver\DocBlockService\UseBlockParser;
use Gacela\Framework\ClassResolver\InMemoryCache;
use Gacela\Framework\Config\Config;
use ReflectionClass;

use function is_string;

trait DocBlockResolverAwareTrait
{
    /** @var array<string,string> */
    protected static array $fileContentCache = [];

    /** @var array<string,?mixed> */
    private array $customServices = [];

    /**
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters = [])
    {
        if (isset($this->customServices[$method])) {
            return $this->customServices[$method];
        }

        $cacheKey = $this->generateCacheKey($method);
        $cache = $this->createCustomServicesCache();


        if (!$cache->has($cacheKey)) {
            $className = $this->getClassFromDoc($method);
            $cache->put($cacheKey, $className);
        }

        /** @psalm-suppress ArgumentTypeCoercion */
        /** @var class-string $className */
        $className = $cache->get($cacheKey);
        $resolvableType = $this->normalizeResolvableType($className);

        $resolved = (new DocBlockServiceResolver($resolvableType))
            ->resolve($className);

        if ($resolved !== null) {
            return $resolved;
        }

        if ($this->hasParentClass()) {
            /** @psalm-suppress ParentNotFound, MixedAssignment, UndefinedMethod */
            $parentReturn = parent::__call($method, $parameters); // @phpstan-ignore-line
            $this->customServices[$method] = $parentReturn;

            return $parentReturn;
        }

        return null;
    }

    private function hasParentClass(): bool
    {
        /** @psalm-suppress ParentNotFound,MixedArgument */
        return class_parents($this)
            && method_exists(parent::class, '__call');
    }

    /**
     * @return class-string
     */
    private function getClassFromDoc(string $method): string
    {
        $reflectionClass = new ReflectionClass(static::class);
        $className = $this->searchClassOverDocBlock($reflectionClass, $method);
        if (class_exists($className)) {
            return $className;
        }
        $className = $this->searchClassOverUseStatements($reflectionClass, $className);
        if (class_exists($className)) {
            return $className;
        }
        throw MissingClassDefinitionException::missingDefinition(static::class, $method, $className);
    }

    private function searchClassOverDocBlock(ReflectionClass $reflectionClass, string $method): string
    {
        $docBlock = (string)$reflectionClass->getDocComment();

        return (new DocBlockParser())->getClassFromMethod($docBlock, $method);
    }

    /**
     * Look the uses, to find the fully-qualified class name for the className.
     */
    private function searchClassOverUseStatements(ReflectionClass $reflectionClass, string $className): string
    {
        $fileName = (string) $reflectionClass->getFileName();
        if (!isset(static::$fileContentCache[$fileName])) {
            static::$fileContentCache[$fileName] = (string) file_get_contents($fileName);
        }
        $phpFile = static::$fileContentCache[$fileName];

        return (new UseBlockParser())->getUseStatement($className, $phpFile);
    }

    private function normalizeResolvableType(string $resolvableType): string
    {
        /** @var list<string> $resolvableTypeParts */
        $resolvableTypeParts = explode('\\', ltrim($resolvableType, '\\'));
        $normalizedResolvableType = end($resolvableTypeParts);

        return is_string($normalizedResolvableType)
            ? $normalizedResolvableType
            : $resolvableType;
    }

    private function generateCacheKey(string $method): string
    {
        return self::class . '::' . $method;
    }

    private function createCustomServicesCache(): ClassNameCacheInterface
    {
        if (!$this->isCacheEnabled()) {
            return new InMemoryCache(CustomServicesCache::class);
        }

        return new CustomServicesCache(
            Config::getInstance()->getCacheDir()
        );
    }

    private function isCacheEnabled(): bool
    {
        return (bool)Config::getInstance()
            ->get(GacelaCache::ENABLED, GacelaCache::DEFAULT_VALUE);
    }
}
