<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\DocBlockService\DocBlockParser;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\ClassResolver\DocBlockService\MissingClassDefinitionException;
use Gacela\Framework\ClassResolver\DocBlockService\UseBlockParser;
use ReflectionClass;

use function is_string;

trait DocBlockResolverAwareTrait
{
    /** @var array<string,string> */
    protected static array $fileContentCache = [];

    /** @var array<string,?object> */
    private array $customServices = [];

    public function __call(string $method, array $arguments = []): ?object
    {
        if (!isset($this->customServices[$method])) {
            $className = $this->getClassFromDoc($method);
            $resolvableType = $this->normalizeResolvableType($className);

            $this->customServices[$method] = (new DocBlockServiceResolver($resolvableType))
                ->resolve($className);
        }

        return $this->customServices[$method];
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
}
