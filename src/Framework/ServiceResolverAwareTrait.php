<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\ServiceResolver\DocBlockResolvable;
use Gacela\Framework\ServiceResolver\DocBlockResolver;

use function array_key_exists;

/**
 * Resolves Gacela services declared via PHPDoc tags or the #[ServiceMap] attribute.
 */
trait ServiceResolverAwareTrait
{
    /** @var array<string,mixed> */
    private array $customServices = [];

    /** @var array<class-string,DocBlockResolver> */
    private static array $docBlockResolvers = [];

    /** @var array<string,DocBlockServiceResolver> */
    private static array $docBlockServiceResolvers = [];

    /**
     * @psalm-suppress LessSpecificImplementedReturnType
     *
     * @param list<mixed> $parameters
     *
     * @return mixed
     */
    public function __call(string $method = '', array $parameters = [])
    {
        if (array_key_exists($method, $this->customServices)) {
            return $this->customServices[$method];
        }

        $resolvable = $this->getDocBlockResolver()->getDocBlockResolvable($method);
        $service = $this->resolveService($resolvable);

        return $this->customServices[$method] = $service;
    }

    private function getDocBlockResolver(): DocBlockResolver
    {
        $callerClass = static::class;

        if (!isset(self::$docBlockResolvers[$callerClass])) {
            self::$docBlockResolvers[$callerClass] = DocBlockResolver::fromCaller($this);
        }

        return self::$docBlockResolvers[$callerClass];
    }

    private function resolveService(DocBlockResolvable $resolvable): object
    {
        $resolvableType = $resolvable->resolvableType();

        if (!isset(self::$docBlockServiceResolvers[$resolvableType])) {
            self::$docBlockServiceResolvers[$resolvableType] = new DocBlockServiceResolver($resolvableType);
        }

        return self::$docBlockServiceResolvers[$resolvableType]->resolve($resolvable->className());
    }
}
