<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\ServiceResolver\DocBlockResolvable;
use Gacela\Framework\ServiceResolver\DocBlockResolver;
use Gacela\Framework\ServiceResolver\ReflectionClassPool;
use Gacela\Framework\ServiceResolver\ServiceMapAccessors;

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
     * @param list<mixed> $parameters
     *
     * @return mixed
     */
    public function __call(string $method = '', array $parameters = [])
    {
        if (array_key_exists($method, $this->customServices)) {
            return $this->customServices[$method];
        }

        $service = $this->resolveMappedPillar($method)
            ?? $this->resolveService($this->getDocBlockResolver()->getDocBlockResolvable($method));

        return $this->customServices[$method] = $service;
    }

    private function resolveMappedPillar(string $method): ?object
    {
        $className = ServiceMapAccessors::classNameFor(ReflectionClassPool::get(static::class), $method);
        $kind = match (true) {
            $className === null => null,
            is_a($className, AbstractFacade::class, true) => 'Facade',
            is_a($className, AbstractFactory::class, true) => 'Factory',
            is_a($className, AbstractConfig::class, true) => 'Config',
            default => null,
        };

        return $className !== null && $kind !== null
            ? AbstractClassResolver::resolveClassName($className, $kind)
            : null;
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
