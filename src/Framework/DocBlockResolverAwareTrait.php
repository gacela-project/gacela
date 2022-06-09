<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\DocBlockService\CustomServicesCache;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockParser;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\ClassResolver\DocBlockService\MissingClassDefinitionException;
use Gacela\Framework\ClassResolver\DocBlockService\UseBlockParser;
use Gacela\Framework\Config\Config;
use ReflectionClass;

use RuntimeException;
use function gettype;
use function is_object;
use function is_string;

trait DocBlockResolverAwareTrait
{
    /** @var array<string,string> */
    protected static array $fileContentCache = [];

    /** @var array<string,?object> */
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
        $className = $cache->get($cacheKey);
        $resolvableType = $this->normalizeResolvableType($className);

        $resolved = (new DocBlockServiceResolver($resolvableType))
            ->resolve($className);

        if ($resolved !== null) {
            return $resolved;
        }

        /**
         * @psalm-suppress ParentNotFound
         * @psalm-suppress MixedArgument
         * @psalm-suppress MixedAssignment
         */
        if (class_parents($this) && method_exists(parent::class, '__call')) {
            $parentReturn = parent::__call($method, $parameters);
            if (!is_object($parentReturn)) {
                throw new RuntimeException('Expected object. Found: ' . gettype($parentReturn));
            }
            $this->customServices[$method] = $parentReturn;

            return $parentReturn;
        }

        return null;
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
        $fileName = $reflectionClass->getFileName();
        if (!isset(static::$fileContentCache[$fileName])) {
            static::$fileContentCache[$fileName] = file_get_contents($fileName);
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

    private function createCustomServicesCache(): CustomServicesCache
    {
        return new CustomServicesCache($this->getCachedClassNamesDir());
    }

    private function getCachedClassNamesDir(): string
    {
        return Config::getInstance()->getAppRootDir() . '/';
    }
}
