<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\CustomService\CustomServiceResolver;
use Gacela\Framework\ClassResolver\CustomService\DocBlockParser;
use Gacela\Framework\ClassResolver\CustomService\MissingMethodException;
use ReflectionClass;

use function is_string;

trait CustomServicesResolverAwareTrait
{
    /** @var array<string,?object> */
    private array $customServices = [];

    public function __call(string $method, array $arguments = []): ?object
    {
        if (!isset($this->customServices[$method])) {
            $className = $this->getClassFromDocComment($method);
            $resolvableType = $this->normalizeResolvableType($className);

            $this->customServices[$method] = (new CustomServiceResolver($resolvableType))
                ->resolve($className);
        }

        return $this->customServices[$method];
    }

    /**
     * @return class-string
     */
    private function getClassFromDocComment(string $method): string
    {
        $reflectionClass = new ReflectionClass(static::class);
        $docBlock = (string)$reflectionClass->getDocComment();

        $repositoryClass = (new DocBlockParser())->getClassFromMethod($docBlock, $method);

        if (class_exists($repositoryClass)) {
            return $repositoryClass;
        }

        throw MissingMethodException::missingOverriding($method, static::class);
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
