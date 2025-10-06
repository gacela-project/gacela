<?php

declare(strict_types=1);

namespace Gacela\Framework\DocBlockResolver;

use Gacela\Framework\ClassResolver\DocBlockService\DocBlockParser;
use Gacela\Framework\ClassResolver\DocBlockService\MissingClassDefinitionException;
use Gacela\Framework\ClassResolver\DocBlockService\UseBlockParser;
use ReflectionClass;

use function is_string;

final class DocBlockResolver
{
    private const SPECIAL_RESOLVABLE_TYPES = ['Facade', 'Factory', 'Config'];

    /** @var array<string,string> [fileName => fileContent] */
    private static array $fileContentCache = [];

    /** @var class-string */
    private readonly string $callerClass;

    /**
     * @param class-string $callerClass
     */
    private function __construct(string $callerClass)
    {
        /** @psalm-suppress PropertyTypeCoercion */
        $this->callerClass = '\\' . ltrim($callerClass, '\\'); // @phpstan-ignore-line
    }

    public static function fromCaller(object $caller): self
    {
        return new self($caller::class);
    }

    public function getDocBlockResolvable(string $method): DocBlockResolvable
    {
        $className = $this->getClassName($method);
        $resolvableType = $this->normalizeResolvableType($className);

        return new DocBlockResolvable($className, $resolvableType);
    }

    /**
     * @return class-string
     */
    private function getClassName(string $method): string
    {
        $cacheKey = $this->generateCacheKey($method);
        $cache = DocBlockResolverCache::getCacheInstance();

        if (!$cache->has($cacheKey)) {
            $className = $this->getClassFromDoc($method);
            $cache->put($cacheKey, $className);
        }

        return $cache->get($cacheKey);
    }

    private function generateCacheKey(string $method): string
    {
        return $this->callerClass . '::' . $method;
    }

    /**
     * @return class-string
     */
    private function getClassFromDoc(string $method): string
    {
        $reflectionClass = new ReflectionClass($this->callerClass);
        $className = $this->searchClassOverDocBlock($reflectionClass, $method);
        if (class_exists($className)) {
            return $className;
        }

        $className = $this->searchClassOverUseStatements($reflectionClass, $className);
        if (class_exists($className)) {
            return $className;
        }

        throw MissingClassDefinitionException::missingDefinition($this->callerClass, $method, $className);
    }

    /**
     * @param ReflectionClass<object> $reflectionClass
     */
    private function searchClassOverDocBlock(ReflectionClass $reflectionClass, string $method): string
    {
        $docBlock = (string)$reflectionClass->getDocComment();

        return (new DocBlockParser())->getClassFromMethod($docBlock, $method);
    }

    /**
     * Look the uses, to find the fully-qualified class name for the className.
     *
     * @param ReflectionClass<object> $reflectionClass
     */
    private function searchClassOverUseStatements(ReflectionClass $reflectionClass, string $className): string
    {
        $fileName = (string)$reflectionClass->getFileName();
        if (!isset(self::$fileContentCache[$fileName])) {
            self::$fileContentCache[$fileName] = (string)file_get_contents($fileName);
        }

        $phpFile = self::$fileContentCache[$fileName];

        return (new UseBlockParser())->getUseStatement($className, $phpFile);
    }

    private function normalizeResolvableType(string $resolvableType): string
    {
        /** @var list<string> $resolvableTypeParts */
        $resolvableTypeParts = explode('\\', $resolvableType);
        $normalizedResolvableType = end($resolvableTypeParts);

        $result = is_string($normalizedResolvableType)
            ? $normalizedResolvableType
            : $resolvableType;

        foreach (self::SPECIAL_RESOLVABLE_TYPES as $specialName) {
            if (str_contains($result, $specialName)) {
                return $specialName;
            }
        }

        return $result;
    }
}
