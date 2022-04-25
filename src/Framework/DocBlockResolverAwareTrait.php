<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\DocBlockService\DocBlockParser;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\ClassResolver\DocBlockService\MissingMethodException;
use ReflectionClass;

use function is_string;

trait DocBlockResolverAwareTrait
{
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
        $docBlock = (string)(new ReflectionClass(static::class))->getDocComment();
        $repositoryClass = (new DocBlockParser())->getClassFromMethod($docBlock, $method);

        if (class_exists($repositoryClass)) {
            return $repositoryClass;
        }

        throw MissingMethodException::missingOverriding($method, static::class, $repositoryClass);
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
