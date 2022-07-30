<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\DocBlockService\DocBlockServiceResolver;
use Gacela\Framework\DocBlockResolver\DocBlockResolvable;

trait DocBlockResolverAwareTrait
{
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

        $docBlockResolvable = DocBlockResolvable::fromCaller($this);

        /** @var class-string $className */
        $className = $docBlockResolvable->getClassName($method);
        $resolvableType = $docBlockResolvable->normalizeResolvableType($className);

        $resolved = (new DocBlockServiceResolver($resolvableType))
            ->resolve($className);

        if ($resolved !== null) {
            return $resolved;
        }

        if ($docBlockResolvable->hasParentClass()) {
            /** @psalm-suppress ParentNotFound, MixedAssignment, UndefinedMethod */
            $parentReturn = parent::__call($method, $parameters); // @phpstan-ignore-line
            $this->customServices[$method] = $parentReturn;

            return $parentReturn;
        }

        return null;
    }
}
